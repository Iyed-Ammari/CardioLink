<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;

class MessageValidator
{
    // Règle 1 & 2
    public function isMessageContentValid(?string $content): bool
    {
        if ($content === null || trim($content) === '') {
            return false;
        }
        
        if (strlen($content) > 2000) {
            return false;
        }

        return true;
    }

    // Règle 3
    public function isValidClassification(string $classification): bool
    {
        $validClassifications = ['NORMAL', 'URGENT', 'ADMINISTRATIF'];
        return in_array(strtoupper($classification), $validClassifications);
    }

    // Règle 4
    public function canPinMessage(Message $message): bool
    {
        $conversation = $message->getConversation();
        if ($conversation === null) {
            return false;
        }
        
        return $conversation->isActive() === true;
    }

    // Règle 5
    public function canStartConversation(User $patient, User $medecin): bool
    {
        if ($patient === $medecin) {
            return false;
        }
        return true;
    }
    // Règle 6 : L'utilisateur fait-il partie de la conversation ?
    public function isUserInConversation(User $user, Conversation $conversation): bool
    {
        return $user === $conversation->getPatient() || $user === $conversation->getMedecin();
    }

    // Règle 7 : L'emoji est-il valide pour la base de données ?
    public function isValidEmoji(?string $emoji): bool
    {
        if ($emoji === null || trim($emoji) === '') {
            return false;
        }
        // On utilise mb_strlen pour compter correctement les emojis (UTF-8)
        return mb_strlen($emoji) <= 10;
    }

    // Règle 8 : Incompatibilité des états
    public function isMessageStateValid(Message $message): bool
    {
        // Un message ne peut pas être archivé ET épinglé en même temps
        if ($message->isPinned() && $message->isArchived()) {
            return false;
        }
        return true;
    }

    // Règle 9 : Détection d'alerte immédiate
    public function requiresImmediateAttention(Message $message): bool
    {
        return $message->getClassification() === 'URGENT' && $message->isRead() === false;
    }

    // Règle 10 : Formatage de l'aperçu de notification
    public function formatNotificationPreview(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return '';
        }
        
        if (mb_strlen($content) > 50) {
            return mb_substr($content, 0, 50) . '...';
        }
        
        return $content;
    }
}