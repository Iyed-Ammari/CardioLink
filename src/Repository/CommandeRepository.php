<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * Pagination des commandes (hors PANIER)
     * @return array{items:Paginator,total:int,pages:int,page:int,limit:int}
     */
    public function paginateNonPanier(int $page = 1, int $limit = 50): array
    {
        $page  = max(1, $page);
        $limit = max(1, min(100, $limit)); // sécurité

        // Important: on join user pour éviter N+1 sur email
        // On ne join pas lignes/produits ici (sinon pagination devient lourde),
        // tes détails restent OK sur une page de 50.
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')->addSelect('u')
            ->andWhere('c.statut != :s')
            ->setParameter('s', Commande::STATUT_PANIER)
            ->orderBy('c.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $pages = (int) ceil($total / $limit);

        return [
            'items' => $paginator,
            'total' => $total,
            'pages' => max(1, $pages),
            'page'  => $page,
            'limit' => $limit,
        ];
    }
}