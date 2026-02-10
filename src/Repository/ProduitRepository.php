<?php
// src/Repository/ProduitRepository.php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }


public function search(
    ?string $q,
    ?string $categorie = null
): array {
    $qb = $this->createQueryBuilder('p');

    if ($q !== null && $q !== '') {
        $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
           ->setParameter('q', '%'.$q.'%');
    }

    if ($categorie !== null && $categorie !== '') {
        $qb->andWhere('p.categorie = :cat')
           ->setParameter('cat', $categorie);
    }

    return $qb->orderBy('p.id', 'DESC')
              ->getQuery()
              ->getResult();
}

/**
 * Catégories réelles depuis la DB
 * Exemple retour :
 * [
 *  "APPAREILS DE MESURE",
 *  "CARDIOLOGIE",
 *  "ACCESSOIRES"
 * ]
 */
public function findExistingCategories(): array
{
    $rows = $this->createQueryBuilder('p')
        ->select('DISTINCT p.categorie')
        ->where('p.categorie IS NOT NULL')
        ->andWhere("p.categorie <> ''")
        ->orderBy('p.categorie', 'ASC')
        ->getQuery()
        ->getScalarResult();

    return array_column($rows, 'categorie');
}

}
