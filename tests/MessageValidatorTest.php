<?php

namespace App\Tests;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Service\MessageValidator;
use PHPUnit\Framework\TestCase;

class MessageValidatorTest extends TestCase
{
    private MessageValidator $validator;

    protected function setUp(): void
    {
        // On initialise notre service avant chaque test
        $this->validator = new MessageValidator();
    }

    // Test 1 : Vérifier les messages vides
    public function testEmptyMessageContentIsInvalid(): void
    {
        $this->assertFalse($this->validator->isMessageContentValid(''));
        $this->assertFalse($this->validator->isMessageContentValid('   '));
        $this->assertFalse($this->validator->isMessageContentValid(null));
        
        // Test positif
        $this->assertTrue($this->validator->isMessageContentValid('Bonjour Docteur !'));
    }

    // Test 2 : Vérifier la limite de caractères
    public function testTooLongMessageIsInvalid(): void
    {
        // On crée une chaîne de 2001 caractères
        $longMessage = str_repeat('a', 2001);
        $this->assertFalse($this->validator->isMessageContentValid($longMessage));
        
        // Une chaîne de 2000 caractères doit passer
        $validMessage = str_repeat('a', 2000);
        $this->assertTrue($this->validator->isMessageContentValid($validMessage));
    }

    // Test 3 : Vérifier la classification IA
    public function testClassificationMustBeInAllowedList(): void
    {
        $this->assertTrue($this->validator->isValidClassification('URGENT'));
        $this->assertTrue($this->validator->isValidClassification('NORMAL'));
        
        // Test négatif avec un faux type
        $this->assertFalse($this->validator->isValidClassification('FAUSSE_ALERTE'));
        $this->assertFalse($this->validator->isValidClassification(''));
    }

    // Test 4 : Vérifier qu'on ne peut pas épingler sur une conversation inactive
    public function testCannotPinMessageInInactiveConversation(): void
    {
        $conversation = new Conversation();
        $conversation->setIsActive(false); // La conversation est clôturée

        $message = new Message();
        $message->setConversation($conversation);

        $this->assertFalse($this->validator->canPinMessage($message));

        // Si on l'active, ça doit retourner vrai
        $conversation->setIsActive(true);
        $this->assertTrue($this->validator->canPinMessage($message));
    }

    // Test 5 : Vérifier qu'un utilisateur ne se parle pas à lui-même
    public function testCannotStartConversationWithYourself(): void
    {
        $user1 = new User();
        // Imaginons qu'ils ont la même référence en mémoire
        
        $this->assertFalse($this->validator->canStartConversation($user1, $user1));

        $user2 = new User();
        $this->assertTrue($this->validator->canStartConversation($user1, $user2));
    }
    // Test 6 : Vérifier que seuls les participants peuvent interagir
    public function testUserMustBePartOfConversationToSendMessage(): void
    {
        $patient = new User();
        $medecin = new User();
        $intrus = new User(); // Un autre utilisateur au hasard

        $conversation = new Conversation();
        $conversation->setPatient($patient);
        $conversation->setMedecin($medecin);

        // Les participants valides
        $this->assertTrue($this->validator->isUserInConversation($patient, $conversation));
        $this->assertTrue($this->validator->isUserInConversation($medecin, $conversation));
        
        // L'intrus doit être bloqué
        $this->assertFalse($this->validator->isUserInConversation($intrus, $conversation));
    }

    // Test 7 : Vérifier la taille des emojis (limite DB = 10)
    public function testReactionEmojiLengthIsValid(): void
    {
        $this->assertTrue($this->validator->isValidEmoji('👍'));
        $this->assertTrue($this->validator->isValidEmoji('heart')); // 5 caractères
        
        // Tests négatifs
        $this->assertFalse($this->validator->isValidEmoji(''));
        $this->assertFalse($this->validator->isValidEmoji(null));
        $this->assertFalse($this->validator->isValidEmoji('this_is_way_too_long')); // > 10 caractères
    }

    // Test 8 : Un message ne peut pas être épinglé ET archivé
    public function testMessageCannotBePinnedAndArchivedAtTheSameTime(): void
    {
        $message = new Message();
        
        // Un message normal est valide
        $message->setIsPinned(false);
        $message->setIsArchived(false);
        $this->assertTrue($this->validator->isMessageStateValid($message));

        // Un message juste épinglé est valide
        $message->setIsPinned(true);
        $message->setIsArchived(false);
        $this->assertTrue($this->validator->isMessageStateValid($message));

        // Les deux en même temps => INVALIDE
        $message->setIsPinned(true);
        $message->setIsArchived(true);
        $this->assertFalse($this->validator->isMessageStateValid($message));
    }

    // Test 9 : L'alerte immédiate (Urgent + Non lu)
    public function testUrgentAndUnreadMessageRequiresImmediateAttention(): void
    {
        $message = new Message();
        
        // Cas 1 : Urgent et Non lu = VRAI
        $message->setClassification('URGENT');
        $message->setIsRead(false);
        $this->assertTrue($this->validator->requiresImmediateAttention($message));

        // Cas 2 : Urgent mais déjà lu = FAUX (plus d'alerte)
        $message->setIsRead(true);
        $this->assertFalse($this->validator->requiresImmediateAttention($message));

        // Cas 3 : Normal et Non lu = FAUX
        $message->setClassification('NORMAL');
        $message->setIsRead(false);
        $this->assertFalse($this->validator->requiresImmediateAttention($message));
    }

    // Test 10 : Coupure du texte des notifications
    public function testNotificationPreviewIsTruncatedProperly(): void
    {
        $shortText = "Bonjour Docteur";
        $this->assertEquals("Bonjour Docteur", $this->validator->formatNotificationPreview($shortText));

        $exact50Text = str_repeat("A", 50);
        $this->assertEquals($exact50Text, $this->validator->formatNotificationPreview($exact50Text));

        // Un texte de 60 caractères doit être coupé à 50 + "..."
        $longText = str_repeat("B", 60);
        $expectedText = str_repeat("B", 50) . '...';
        $this->assertEquals($expectedText, $this->validator->formatNotificationPreview($longText));
        
        // Test de sécurité avec du null
        $this->assertEquals('', $this->validator->formatNotificationPreview(null));
    }
}