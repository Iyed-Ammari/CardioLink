<?php

namespace App\Tests;

use App\Entity\Post;
use App\Entity\User;
use App\Service\PostManager;
use PHPUnit\Framework\TestCase;

class PostManagerTest extends TestCase
{
    private PostManager $postManager;

    protected function setUp(): void
    {
        $this->postManager = new PostManager();
    }

    public function testTitleTooShortFails(): void
    {
        $post = new Post();
        $post->setTitle("Cœur"); // 4 caractères
        $this->assertFalse($this->postManager->validateTitle($post));
    }

    public function testLikesCannotBeNegative(): void
    {
        $post = new Post();
        $post->setLikes(-5); // Forçage d'une valeur négative
        $this->assertFalse($this->postManager->hasValidLikes($post));
    }

    public function testAICanOnlyAnalyzeLongContent(): void
    {
        $post = new Post();
        $post->setContent("Mal au bras"); // Trop court pour l'IA
        $this->assertFalse($this->postManager->canBeAnalyzedByAI($post));

        $post->setContent("Je ressens une douleur persistante dans le bras gauche après mon effort."); 
        $this->assertTrue($this->postManager->canBeAnalyzedByAI($post));
    }

    public function testPostIsIncompleteWithoutUser(): void
    {
        $post = new Post();
        $post->setTitle("Titre valide");
        $post->setContent("Contenu valide et assez long pour le test.");
        // On ne définit pas de User
        
        $this->assertFalse($this->postManager->isPostComplete($post));
    }

    public function testPostIsCompleteWithAllFields(): void
    {
        $user = $this->createMock(User::class);
        $post = new Post();
        $post->setTitle("Titre valide");
        $post->setContent("Contenu valide et assez long.");
        $post->setUser($user);

        $this->assertTrue($this->postManager->isPostComplete($post));
    }
}