<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function findPanierByUser(User $user): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :u')
            ->andWhere('c.statut = :s')
            ->setParameter('u', $user)
            ->setParameter('s', Commande::STATUT_PANIER)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Commande[] */
    public function findCommandesByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :u')
            ->setParameter('u', $user)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Commande[] */
    public function findAllNonPanier(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut != :s')
            ->setParameter('s', Commande::STATUT_PANIER)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
