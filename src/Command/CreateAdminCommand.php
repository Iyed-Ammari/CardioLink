<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un utilisateur ADMIN (sécurisé) pour accéder à /admin'
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ✅ Modifie ces infos si tu veux
        $email = 'admin@cardiolink.tn';
        $plainPassword = 'Admin12345';
        $nom = 'Admin';
        $prenom = 'CardioLink';
        $tel = '00000000';
        $adresse = 'Tunis';

        // 1) Vérifier si l'email existe déjà
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln("<error>❌ Cet email existe déjà : $email</error>");
            return Command::FAILURE;
        }

        // 2) Créer user
        $user = new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setTel($tel);
        $user->setAdresse($adresse);

        // 3) Rôle admin
        $user->setRoles(['ROLE_ADMIN']);

        // 4) Password hashé (sécurisé)
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        // 5) Verified
        $user->setIsVerified(true);

        // 6) Sauvegarder
        $this->em->persist($user);
        $this->em->flush();

        $output->writeln("<info>✅ Admin créé</info>");
        $output->writeln("Email: $email");
        $output->writeln("Password: $plainPassword");

        return Command::SUCCESS;
    }
}
