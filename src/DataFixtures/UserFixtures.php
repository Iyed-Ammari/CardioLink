<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\DossierMedical;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class UserFixtures extends Fixture
{
    private $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $groupes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

        // Création de 10 Patients de test
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail("patient$i@cardiolink.com");
            $user->setNom($faker->lastName);
            $user->setPrenom($faker->firstName);
            $user->setTel("2" . $faker->randomNumber(7, true)); // Format 8 chiffres tunisien
            $user->setAdresse($faker->address);
            $user->setRoles(['ROLE_PATIENT']);
            
            // Password générique pour les tests : "password"
            $password = $this->hasher->hashPassword($user, 'password');
            $user->setPassword($password);

            // Création du Dossier Médical lié
            $dossier = new DossierMedical();
            $dossier->setGroupeSanguin($faker->randomElement($groupes));
            $dossier->setAntecedents($faker->sentence(10));
            $dossier->setAllergies($faker->words(3, true));
            $dossier->setUser($user); // Liaison OneToOne

            $manager->persist($user);
            $manager->persist($dossier);
        }

        $manager->flush();
    }
}