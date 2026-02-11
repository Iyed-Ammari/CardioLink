<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
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
    ) {}

    #[Route('', name: 'conversation_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $conversations = $this->conversationRepository->findByUser($user);

        return $this->render('conversation/index.html.twig', [
            'conversations' => $conversations,
        ]);
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
