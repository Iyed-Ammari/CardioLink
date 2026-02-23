<?php

namespace App\Repository;

use App\Entity\PostSummary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostSummary>
 */
class PostSummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostSummary::class);
    }

    // Ajoutez ici des méthodes personnalisées si besoin
}
