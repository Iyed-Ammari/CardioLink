<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
final class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * @return Produit[]
     */
    public function search(
        ?string $q,
        ?string $categorie = null,
        ?float $minPrix = null,
        ?float $maxPrix = null,
        ?string $stockStatus = null,
        int $page = 1,
        int $limit = 10
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

        if ($minPrix !== null) {
            $qb->andWhere('p.prix >= :minPrix')
               ->setParameter('minPrix', $minPrix);
        }

        if ($maxPrix !== null) {
            $qb->andWhere('p.prix <= :maxPrix')
               ->setParameter('maxPrix', $maxPrix);
        }

        if ($stockStatus !== null && $stockStatus !== '') {
            if ($stockStatus === 'RUPTURE') {
                $qb->andWhere('p.stock = 0');
            } elseif ($stockStatus === 'DISPONIBLE') {
                $qb->andWhere('p.stock > 0');
            }
        }

        $offset = ($page - 1) * $limit;

        return $qb->orderBy('p.id', 'DESC')
                  ->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * @return string[]
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