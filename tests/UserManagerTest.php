<?php
namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    private UserManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UserManager();
    }

    // Test 1 : User valide
    public function testUserValide(): void
    {
        $user = new User();
        $user->setNom('Hajjeji');
        $user->setPrenom('Sarra');
        $user->setEmail('sarra@test.com');

        $this->assertTrue($this->manager->validate($user));
    }

    // Test 2 : Nom vide
    public function testNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $user = new User();
        $user->setNom('');
        $user->setPrenom('Sarra');
        $user->setEmail('sarra@test.com');

        $this->manager->validate($user);
    }

    // Test 3 : Prénom vide
    public function testPrenomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom est obligatoire');

        $user = new User();
        $user->setNom('Hajjeji');
        $user->setPrenom('');
        $user->setEmail('sarra@test.com');

        $this->manager->validate($user);
    }

    // Test 4 : Email invalide
    public function testEmailInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email est invalide');

        $user = new User();
        $user->setNom('Hajjeji');
        $user->setPrenom('Sarra');
        $user->setEmail('email_invalide');

        $this->manager->validate($user);
    }

    // Test 5 : Email vide
    public function testEmailVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email est obligatoire');

        $user = new User();
        $user->setNom('Hajjeji');
        $user->setPrenom('Sarra');
        $user->setEmail('');

        $this->manager->validate($user);
    }
}