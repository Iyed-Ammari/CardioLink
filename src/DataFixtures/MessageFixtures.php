<?php

namespace App\DataFixtures;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class MessageFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // 1. Création des utilisateurs et conversation (pour l'exemple)
        $patient = new User();
        $patient->setEmail('patient@test.com')->setPassword('123456')->setNom('Ben Ali')->setPrenom('Ahmed')->setRoles(['ROLE_PATIENT'])->setTel('12345678')->setAdresse('Tunis');
        
        $medecin = new User();
        $medecin->setEmail('doc@test.com')->setPassword('123456')->setNom('Dr. Tounsi')->setPrenom('Sami')->setRoles(['ROLE_MEDECIN'])->setTel('87654321')->setAdresse('Ariana');
        
        $manager->persist($patient);
        $manager->persist($medecin);

        $conversation = new Conversation();
        $conversation->setPatient($patient)->setMedecin($medecin)->setCreatedAt(new \DateTime())->setUpdatedAt(new \DateTime())->setIsActive(true);
        $manager->persist($conversation);

        // 2. LE DATASET D'ENTRAÎNEMENT (Contenu + Label)
        $dataset = [
            // URGENCES (Symptômes graves)
            ['content' => "J'ai une douleur intense dans la poitrine gauche", 'label' => 'URGENT'],
            ['content' => "Je n'arrive plus à respirer correctement, c'est bloqué", 'label' => 'URGENT'],
            ['content' => "Mon bras s'engourdit et j'ai des vertiges", 'label' => 'URGENT'],
            ['content' => "Je sens mon cœur qui bat trop vite, je vais m'évanouir", 'label' => 'URGENT'],
            ['content' => "Il y a du sang quand je tousse, aidez-moi", 'label' => 'URGENT'],
            ['content' => "Ma vision est floue et j'ai très mal à la tête", 'label' => 'URGENT'],
            ['content' => "Douleur thoracique qui ne passe pas depuis 1 heure", 'label' => 'URGENT'],

            // ADMINISTRATIF (RDV, Papiers)
            ['content' => "Bonjour, est-ce que je peux décaler mon rendez-vous ?", 'label' => 'ADMINISTRATIF'],
            ['content' => "Pouvez-vous m'envoyer la facture de la dernière consultation ?", 'label' => 'ADMINISTRATIF'],
            ['content' => "A quelle heure ouvrez-vous le cabinet demain ?", 'label' => 'ADMINISTRATIF'],
            ['content' => "Est-ce que vous acceptez la carte bancaire ?", 'label' => 'ADMINISTRATIF'],
            ['content' => "J'ai besoin d'un certificat médical pour le sport", 'label' => 'ADMINISTRATIF'],
            ['content' => "Combien coûte une consultation simple ?", 'label' => 'ADMINISTRATIF'],

            // NORMAL (Suivi, Politesse)
            ['content' => "Bonjour docteur, je me sens beaucoup mieux merci", 'label' => 'NORMAL'],
            ['content' => "J'ai bien pris mes médicaments ce matin", 'label' => 'NORMAL'],
            ['content' => "Merci pour votre réponse rapide", 'label' => 'NORMAL'],
            ['content' => "Bonne journée à vous", 'label' => 'NORMAL'],
            ['content' => "La pharmacie n'avait pas le générique, j'ai pris l'original", 'label' => 'NORMAL'],
            ['content' => "Ok c'est noté, à lundi", 'label' => 'NORMAL'],
        ];

        // 3. Génération de 100 messages aléatoires basés sur ce dataset
        for ($i = 0; $i < 100; $i++) {
            $data = $dataset[array_rand($dataset)]; // On prend une phrase au hasard
            
            $msg = new Message();
            $msg->setConversation($conversation);
            $msg->setSender($faker->boolean(50) ? $patient : $medecin);
            $msg->setContent($data['content']); // Le texte
            $msg->setClassification($data['label']); // La VRAIE réponse (pour apprendre)
            $msg->setCreatedAt($faker->dateTimeBetween('-1 month', 'now'));
            $msg->setIsRead(true);

            $manager->persist($msg);
        }

        $manager->flush();
    }
}