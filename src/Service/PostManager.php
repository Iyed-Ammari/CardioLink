<?php

namespace App\Service;

use App\Entity\Post;

class PostManager
{
    /**
     * Règle 1 : Un post de qualité doit avoir un titre d'au moins 8 caractères (Exemple 9)
     */
    public function validateTitle(Post $post): bool
    {
        return strlen($post->getTitle() ?? '') >= 8;
    }

    /**
     * Règle 2 : Le système de Likes ne peut pas être négatif (Exemple 1)
     */
    public function hasValidLikes(Post $post): bool
    {
        return $post->getLikes() >= 0;
    }

    /**
     * Règle 3 : Un post ne peut être traité par l'IA que s'il a un contenu suffisant (>20 chars)
     */
    public function canBeAnalyzedByAI(Post $post): bool
    {
        return strlen($post->getContent() ?? '') > 20;
    }

    /**
     * Règle 4 : Un post est considéré comme "Complet" s'il a un titre, un contenu et un auteur (Exemple 4)
     */
    public function isPostComplete(Post $post): bool
    {
        return !empty($post->getTitle()) && !empty($post->getContent()) && $post->getUser() !== null;
    }
}