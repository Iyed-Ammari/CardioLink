<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PanierExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly CommandeRepository $commandeRepo,
        private readonly EntityManagerInterface $em
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('panier_count', [$this, 'getPanierCount']),
            new TwigFunction('panier_total', [$this, 'getPanierTotal']),
        ];
    }

    private function getLocalUser(): ?User
    {
        $user = $this->security->getUser();
        if ($user instanceof User) return $user;

        $email = 'patient@cardiolink.tn';
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function getPanierCount(): int
    {
        $user = $this->getLocalUser();
        if (!$user) return 0;

        $panier = $this->commandeRepo->findPanierByUser($user);
        if (!$panier) return 0;

        $count = 0;
        foreach ($panier->getLignes() as $l) $count += (int) $l->getQuantite();
        return $count;
    }

    public function getPanierTotal(): string
    {
        $user = $this->getLocalUser();
        if (!$user) return '0.00';

        $panier = $this->commandeRepo->findPanierByUser($user);
        if (!$panier) return '0.00';

        return (string) $panier->getMontantTotal();
    }
}
