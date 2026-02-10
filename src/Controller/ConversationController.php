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
}
