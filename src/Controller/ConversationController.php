<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Intervention;
use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\InterventionRepository;
use App\Repository\MessageReactionRepository;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/messages', name: 'app_')]
#[IsGranted('ROLE_USER')]
class ConversationController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private MessageReactionRepository $reactionRepository,
        private NotificationRepository $notificationRepository,
        private UserRepository $userRepository,
        private InterventionRepository $interventionRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'conversation_index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sortBy', 'updated');
        $order = $request->query->get('order', 'DESC');
        
        if (!in_array($sortBy, ['updated', 'created', 'contact', 'status'])) {
            $sortBy = 'updated';
        }
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }
        
        $conversations = $this->conversationRepository->findByUserWithSearchAndSort(
            $user,
            $search ?: null,
            $sortBy,
            $order
        );

        $contacts = [];
        if ($this->isGranted('ROLE_MEDECIN')) {
            $contacts = $this->userRepository->findPatients();
        } else {
            $contacts = $this->userRepository->findMedecins();
        }

        return $this->render('conversation/index.html.twig', [
            'conversations' => $conversations,
            'contacts' => $contacts,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order
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
    public function markAsRead(Message $message, EntityManagerInterface $entityManager): JsonResponse
    {
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

        $hasUpdates = false;
        foreach ($messages as $message) {
            if ($message->getSender() !== $currentUser && !$message->isRead()) {
                $message->setIsRead(true);
                $hasUpdates = true;
            }
        }

        if ($hasUpdates) {
            $entityManager->flush();
        }

        return $this->render('conversation/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    #[Route('/{id}/send', name: 'conversation_send', methods: ['POST'])]
    public function sendMessage(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $entityManager,
        HttpClientInterface $client
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (!$content) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        $message = new Message();
        $message->setContent($content);
        $message->setConversation($conversation);
        $message->setSender($this->getUser());
        $message->setCreatedAt(new \DateTime());
        $message->setIsRead(false);

        $classification = 'NORMAL';
        try {
            $response = $client->request('POST', 'http://127.0.0.1:5000/analyze_message', [
                'json' => ['content' => $content]
            ]);

            if ($response->getStatusCode() === 200) {
                $aiResult = $response->toArray();
                $classification = $aiResult['classification'] ?? 'NORMAL';
            }
        } catch (\Exception $e) {
            // IA non disponible
        }

        $message->setClassification($classification);

        if ($classification === 'URGENT') {
            $intervention = new Intervention();
            $intervention->setType('Alerte SOS');
            $intervention->setDescription('Alerte SOS générée automatiquement: ' . $content);
            $intervention->setStatut('En attente');
            $intervention->setDatePlanifiee(new \DateTimeImmutable());
            $intervention->setMedecin($conversation->getMedecin());
            
            $entityManager->persist($intervention);
        }

        $conversation->setUpdatedAt(new \DateTime());
        $entityManager->persist($message);
        $entityManager->flush();

        // Créer une notification pour l'autre utilisateur
        $recipient = $conversation->getPatient();
        if ($this->getUser() === $recipient) {
            $recipient = $conversation->getMedecin();
        }

        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setSender($this->getUser());
        $notification->setConversation($conversation);
        $notification->setMessage($message);
        $notification->setContent($content);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTime());

        $entityManager->persist($notification);
        $entityManager->flush();

        $user = $this->getUser();
        $nomComplet = 'Moi';
        if ($user instanceof User) {
            $nomComplet = $user->getPrenom() . ' ' . $user->getNom();
        }

        return $this->json([
            'status' => 'success',
            'messageId' => $message->getId(),
            'senderName' => $nomComplet,
            'createdAt' => $message->getCreatedAt()->format('H:i'),
            'classification' => $classification
        ]);
    }

    #[Route('/{id}/suggestions', name: 'conversation_suggestions', methods: ['POST'])]
    public function getSuggestions(Conversation $conversation, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $input = strtolower(trim($data['input'] ?? ''));

        if (strlen($input) < 2) {
            return $this->json(['suggestions' => []]);
        }

        $medicalTerms = [
            'Bonjour Dr', 'Bonjour patient', 'Comment allez-vous', 'Je vais bien',
            'Rendez-vous demain', 'Prendre un rendez-vous', 'Urgent - assistance',
            'Tension artérielle', 'Fréquence cardiaque', 'Douleur thoracique',
            'Essoufflement', 'Palpitations', 'Merci beaucoup', 'Au revoir',
            'À bientôt', 'D\'accord', 'Je comprends', 'Pouvez-vous',
            'Quelle heure', 'Quel jour',
        ];

        $messages = $this->messageRepository->findByConversation($conversation);
        
        $recentPhrases = [];
        foreach (array_slice($messages, -10) as $message) {
            $content = $message->getContent();
            $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($sentences as $sentence) {
                $phrase = trim($sentence);
                if (strlen($phrase) > 3) {
                    $recentPhrases[] = $phrase;
                }
            }
        }

        $allSuggestions = array_merge($medicalTerms, $recentPhrases);
        $suggestions = [];
        foreach (array_unique($allSuggestions) as $suggestion) {
            if (stripos($suggestion, $input) === 0 && count($suggestions) < 8) {
                $suggestions[] = $suggestion;
            }
        }

        return $this->json(['suggestions' => $suggestions]);
    }

    #[Route('/message/{id}/react', name: 'message_react', methods: ['POST'])]
    public function addReaction(Message $message, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $emoji = $data['emoji'] ?? null;

        if (!$emoji) {
            return $this->json(['error' => 'Emoji manquant'], 400);
        }

        $user = $this->getUser();
        $existingReaction = $this->reactionRepository->findReaction($message, $user, $emoji);

        if ($existingReaction) {
            $this->entityManager->remove($existingReaction);
            $this->entityManager->flush();
            
            return $this->json([
                'status' => 'removed',
                'emoji' => $emoji,
                'reactions' => $this->getMessageReactions($message)
            ]);
        } else {
            $reaction = new MessageReaction();
            $reaction->setMessage($message);
            $reaction->setUser($user);
            $reaction->setEmoji($emoji);

            $this->entityManager->persist($reaction);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'added',
                'emoji' => $emoji,
                'reactions' => $this->getMessageReactions($message)
            ]);
        }
    }

    private function getMessageReactions(Message $message): array
    {
        $summary = $this->reactionRepository->findReactionsSummary($message);
        $currentUser = $this->getUser();
        
        $formattedReactions = [];
        foreach ($summary as $item) {
            $emoji = $item['emoji'];
            $count = $item['count'];
            $hasReacted = $currentUser ? (bool) $this->reactionRepository->findReaction($message, $currentUser, $emoji) : false;
            
            $formattedReactions[] = [
                'emoji' => $emoji,
                'count' => $count,
                'hasReacted' => $hasReacted
            ];
        }
        return $formattedReactions;
    }
 
    #[Route('/{conversationId}/message/{messageId}/pin', name: 'message_pin', methods: ['POST'])]
    public function togglePin(int $conversationId, int $messageId, EntityManagerInterface $entityManager): JsonResponse
    {
        $conversation = $this->conversationRepository->find($conversationId);
        $message = $this->messageRepository->find($messageId);

        if (!$conversation || !$message || $message->getConversation()->getId() !== $conversation->getId()) {
            return $this->json(['error' => 'Message non trouvé'], 404);
        }

        $message->setIsPinned(!$message->isPinned());
        $entityManager->flush();

        return $this->json([
            'status' => 'success',
            'isPinned' => $message->isPinned(),
            'message' => 'Message ' . ($message->isPinned() ? 'épinglé' : 'dépinglé') . ' avec succès'
        ]);
    }

    #[Route('/{conversationId}/message/{messageId}/archive', name: 'message_archive', methods: ['POST'])]
    public function toggleArchive(int $conversationId, int $messageId, EntityManagerInterface $entityManager): JsonResponse
    {
        $conversation = $this->conversationRepository->find($conversationId);
        $message = $this->messageRepository->find($messageId);

        if (!$conversation || !$message || $message->getConversation()->getId() !== $conversation->getId()) {
            return $this->json(['error' => 'Message non trouvé'], 404);
        }

        $message->setIsArchived(!$message->isArchived());
        $entityManager->flush();

        return $this->json([
            'status' => 'success',
            'isArchived' => $message->isArchived(),
            'message' => 'Message ' . ($message->isArchived() ? 'archivé' : 'désarchivé') . ' avec succès'
        ]);
    }

    // NOUVELLE METHODE DYNAMIQUE (Remplace getPinned et getArchived)
    #[Route('/{id}/filter/{type}', name: 'conversation_filter_messages', methods: ['GET'])]
    public function getFilteredMessages(Conversation $conversation, string $type): JsonResponse
    {
        if ($type === 'pinned') {
            $filteredMessages = $this->messageRepository->findPinnedByConversation($conversation);
        } elseif ($type === 'archived') {
            $filteredMessages = $this->messageRepository->findArchivedByConversation($conversation);
        } else {
            return $this->json(['error' => 'Type de filtre non valide'], 400);
        }

        $messages = [];
        foreach ($filteredMessages as $message) {
            $messages[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => $message->getSender()->getPrenom() . ' ' . $message->getSender()->getNom(),
                'createdAt' => $message->getCreatedAt()->format('d/m/Y H:i')
            ];
        }

        return $this->json(['messages' => $messages]);
    }

    // === GESTION DES NOTIFICATIONS ===

    #[Route('/notifications/unread', name: 'notifications_unread', methods: ['GET'])]
    public function getUnreadNotifications(): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $this->notificationRepository->findUnreadByUser($user);

        $data = [];
        foreach ($notifications as $notification) {
            $data[] = [
                'id' => $notification->getId(),
                'senderName' => $notification->getSender()->getPrenom() . ' ' . $notification->getSender()->getNom(),
                'senderPrenom' => $notification->getSender()->getPrenom(),
                'conversationId' => $notification->getConversation()->getId(),
                'content' => substr($notification->getContent(), 0, 50) . (strlen($notification->getContent()) > 50 ? '...' : ''),
                'createdAt' => $notification->getCreatedAt()->format('H:i'),
                'type' => ($user === $notification->getConversation()->getPatient() ? 'Dr. ' : '')
            ];
        }

        return $this->json([
            'notifications' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'notification_mark_read', methods: ['POST'])]
    public function markNotificationAsRead(Notification $notification): JsonResponse
    {
        $notification->setIsRead(true);
        $this->entityManager->flush();

        return $this->json(['status' => 'success']);
    }

    #[Route('/notifications/count', name: 'notifications_count', methods: ['GET'])]
    public function countUnreadNotifications(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->notificationRepository->countUnreadByUser($user);

        return $this->json(['count' => $count]);
    }

    #[Route('/{conversationId}/mark-notifications-read', name: 'mark_conversation_notifications_read', methods: ['POST'])]
    public function markConversationNotificationsRead(int $conversationId): JsonResponse
    {
        $user = $this->getUser();
        $conversation = $this->conversationRepository->find($conversationId);

        if (!$conversation) {
            return $this->json(['status' => 'error', 'message' => 'Conversation not found'], 404);
        }

        // Récupérer toutes les notifications non lues de cette conversation pour cet utilisateur
        $notifications = $this->entityManager->createQuery(
            'SELECT n FROM App\Entity\Notification n 
             WHERE n.recipient = :user 
             AND n.conversation = :conversation 
             AND n.isRead = false'
        )
        ->setParameter('user', $user)
        ->setParameter('conversation', $conversation)
        ->getResult();

        // Marquer toutes comme lues
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'All notifications marked as read',
            'count' => count($notifications)
        ]);
    }
}