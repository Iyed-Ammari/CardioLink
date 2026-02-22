<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface; // <--- N'oublie pas cet import !

#[Route('/messages', name: 'app_')]
#[IsGranted('ROLE_USER')]
class ConversationController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'conversation_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $conversations = $this->conversationRepository->findByUser($user);

        $contacts = [];
        if ($this->isGranted('ROLE_MEDECIN')) {
            $contacts = $this->userRepository->findPatients(); // Utilise ta méthode repository personnalisée
        } else {
            $contacts = $this->userRepository->findMedecins(); // Utilise ta méthode repository personnalisée
        }

        return $this->render('conversation/index.html.twig', [
            'conversations' => $conversations,
            'contacts' => $contacts
        ]);
    }

    #[Route('/start/{id}', name: 'conversation_start')]
    public function start(User $recipient): Response
    {
        $currentUser = $this->getUser();

        if ($currentUser === $recipient) {
            $this->addFlash('danger', 'Vous ne pouvez pas parler à vous-même.');
            return $this->redirectToRoute('app_conversation_index');
        }

        $existingConversation = $this->conversationRepository->findByPatientAndMedecin($currentUser, $recipient);

        if ($existingConversation) {
            return $this->redirectToRoute('app_conversation_show', ['id' => $existingConversation->getId()]);
        }

        $conversation = new Conversation();

        if ($this->isGranted('ROLE_MEDECIN')) {
            $conversation->setMedecin($currentUser);
            $conversation->setPatient($recipient);
        } else {
            $conversation->setPatient($currentUser);
            $conversation->setMedecin($recipient);
        }

        $conversation->setCreatedAt(new \DateTime());
        $conversation->setUpdatedAt(new \DateTime());
        $conversation->setIsActive(true);

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_conversation_show', ['id' => $conversation->getId()]);
    }
    #[Route('/message/{id}/read', name: 'message_read', methods: ['POST'])]
    public function markAsRead(\App\Entity\Message $message, EntityManagerInterface $entityManager): JsonResponse
    {
        // Si le message n'est pas déjà lu, on le marque
        if (!$message->isRead()) {
            $message->setIsRead(true);
            $entityManager->flush();
        }
        return $this->json(['status' => 'success']);
    }
    #[Route('/{id}', name: 'conversation_show', requirements: ['id' => '\d+'])]
    public function show(Conversation $conversation, EntityManagerInterface $entityManager): Response
    {
        $messages = $this->messageRepository->findByConversation($conversation);
        $currentUser = $this->getUser();

        // --- 👁️ MISE À JOUR "VU" ---
        $hasUpdates = false;
        foreach ($messages as $message) {
            // Si le message vient de l'autre ET n'est pas encore lu
            if ($message->getSender() !== $currentUser && !$message->isRead()) {
                $message->setIsRead(true);
                $hasUpdates = true;
            }
        }

        if ($hasUpdates) {
            $entityManager->flush();
        }
        // ---------------------------

        return $this->render('conversation/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    // ✅ METHODE MODIFIÉE POUR L'IA
    #[Route('/{id}/send', name: 'conversation_send', methods: ['POST'])]
    public function sendMessage(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $entityManager,
        HttpClientInterface $client // <--- Injection du client HTTP pour parler à Python
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (!$content) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        $message = new \App\Entity\Message();
        $message->setContent($content);
        $message->setConversation($conversation);
        $message->setSender($this->getUser());
        $message->setCreatedAt(new \DateTime());
        $message->setIsRead(false);

        // --- 🤖 APPEL IA : DÉBUT ---
        $classification = 'NORMAL'; // Valeur par défaut
        try {
            // On appelle ton script Python qui tourne sur le port 5000
            $response = $client->request('POST', 'http://127.0.0.1:5000/analyze_message', [
                'json' => ['content' => $content]
            ]);

            if ($response->getStatusCode() === 200) {
                $aiResult = $response->toArray();
                $classification = $aiResult['classification'] ?? 'NORMAL';
            }
        } catch (\Exception $e) {
            // Si l'IA est éteinte ou plante, on ne bloque pas l'envoi du message
            // On garde la classification 'NORMAL' par défaut
        }

        // On enregistre le résultat de l'IA en BDD
        $message->setClassification($classification);
        // --- 🤖 APPEL IA : FIN ---

        $conversation->setUpdatedAt(new \DateTime());

        $entityManager->persist($message);
        $entityManager->flush();

        // Récupération du nom pour l'affichage
        $user = $this->getUser();
        $nomComplet = 'Moi';
        if ($user instanceof \App\Entity\User) {
            $nomComplet = $user->getPrenom() . ' ' . $user->getNom();
        }

        // On renvoie la réponse JSON incluant la classification pour le JS
        return $this->json([
            'status' => 'success',
            'messageId' => $message->getId(),
            'senderName' => $nomComplet,
            'createdAt' => $message->getCreatedAt()->format('H:i'),
            'classification' => $classification // <--- C'est ici que le front récupère l'info !
        ]);
    }
}
