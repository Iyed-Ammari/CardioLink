<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\User; // N'oublie pas d'importer User
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository; // Ajoute ça
use Doctrine\ORM\EntityManagerInterface; // Ajoute ça
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages', name: 'app_')]
#[IsGranted('ROLE_USER')]
class ConversationController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private UserRepository $userRepository, // Injection du UserRepository
        private EntityManagerInterface $entityManager // Injection de l'EntityManager
    ) {}

    #[Route('', name: 'conversation_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // 1. Récupérer les conversations existantes
        $conversations = $this->conversationRepository->findByUser($user);

        // 2. Récupérer la liste des contacts potentiels (Logique inversée)
        $contacts = [];
        if ($this->isGranted('ROLE_MEDECIN')) {
            // Si je suis médecin, je veux voir les patients
            $contacts = $this->userRepository->findPatients();
        } else {
            // Si je suis patient, je veux voir les médecins
            $contacts = $this->userRepository->findMedecins();
        }

        return $this->render('conversation/index.html.twig', [
            'conversations' => $conversations,
            'contacts' => $contacts // On envoie la liste à la vue
        ]);
    }

    // ✅ NOUVELLE METHODE : Créer ou Rediriger vers une conversation
    #[Route('/start/{id}', name: 'conversation_start')]
    public function start(User $recipient): Response
    {
        $currentUser = $this->getUser();

        // Vérification de sécurité simple
        if ($currentUser === $recipient) {
            $this->addFlash('danger', 'Vous ne pouvez pas parler à vous-même.');
            return $this->redirectToRoute('app_conversation_index');
        }

        // 1. Vérifier si une conversation existe déjà
        $existingConversation = $this->conversationRepository->findByPatientAndMedecin($currentUser, $recipient);

        if ($existingConversation) {
            // Si elle existe, on redirige directement dessus
            return $this->redirectToRoute('app_conversation_show', ['id' => $existingConversation->getId()]);
        }

        // 2. Si elle n'existe pas, on la crée
        $conversation = new Conversation();
        
        // On détermine qui est qui en fonction des rôles
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
    #[Route('/{id}', name: 'conversation_show', requirements: ['id' => '\d+'])]
    public function show(Conversation $conversation): Response
    {
        $messages = $this->messageRepository->findByConversation($conversation);

        return $this->render('conversation/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
    #[Route('/{id}/send', name: 'conversation_send', methods: ['POST'])]
    public function sendMessage(Conversation $conversation, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\JsonResponse
    {
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

        // Mise à jour du timestamp de la conversation
        $conversation->setUpdatedAt(new \DateTime());

        $entityManager->persist($message);
        $entityManager->flush();
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $nom = $user->getNom();
            $prenom = $user->getPrenom();
        }

        return $this->json([
            'status' => 'success', 
            'messageId' => $message->getId(),
            'senderName' => $prenom . ' ' . $nom,
            'createdAt' => $message->getCreatedAt()->format('H:i')
        ]);
    }
}
