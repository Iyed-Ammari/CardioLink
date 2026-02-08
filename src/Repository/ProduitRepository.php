<?php

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
        ?float $minPrix,
        ?float $maxPrix,
        ?string $stockStatus,
        int $page = 1,
        int $limit = 10,
        ?string $categorie = null
    ): array {
        $qb = $this->createQueryBuilder('p');

        if ($q) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        if ($minPrix !== null) {
            $qb->andWhere('p.prix >= :minPrix')->setParameter('minPrix', $minPrix);
        }

        if ($maxPrix !== null) {
            $qb->andWhere('p.prix <= :maxPrix')->setParameter('maxPrix', $maxPrix);
        }

        if ($stockStatus === 'RUPTURE') {
            $qb->andWhere('p.stock <= 0');
        } elseif ($stockStatus === 'DISPONIBLE') {
            $qb->andWhere('p.stock > 0');
        }

        if ($categorie !== null && $categorie !== '') {
            $qb->andWhere('p.categorie = :cat')
               ->setParameter('cat', strtoupper($categorie));
        }

        $qb->orderBy('p.id', 'DESC')
           ->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * IDs des produits "verrouillés" (référencés par une FK),
     * compatible MySQL/MariaDB (et PostgreSQL si besoin).
     */
    public function findLockedProductIds(): array
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $meta = $em->getClassMetadata(Produit::class);
        $produitTable = $meta->getTableName();
        $pkColumn = $meta->getIdentifierColumnNames()[0] ?? 'id';

        // ✅ Compat DBAL 2/3/4 : on récupère le nom de plateforme sans getName()
        $platformObj = $conn->getDatabasePlatform();
        $platformClass = strtolower((new \ReflectionClass($platformObj))->getShortName()); // ex: mariadbplatform / mysqlplatform / postgresqlplatform

        $isMysqlFamily = str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb');
        $isPostgres = str_contains($platformClass, 'postgres');

        $locked = [];

        // ---------- MySQL / MariaDB ----------
        if ($isMysqlFamily) {
            $sql = "
                SELECT TABLE_NAME as table_name, COLUMN_NAME as column_name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                  AND REFERENCED_TABLE_NAME = :tbl
                  AND REFERENCED_COLUMN_NAME = :pk
            ";
            $refs = $conn->fetchAllAssociative($sql, ['tbl' => $produitTable, 'pk' => $pkColumn]);

            if (!$refs) {
                return [];
            }

            $parts = [];
            foreach ($refs as $r) {
                $t = $r['table_name'];
                $c = $r['column_name'];

                // SELECT DISTINCT produit_id FROM ligne_commande ...
                $parts[] = "SELECT DISTINCT `$c` AS pid FROM `$t` WHERE `$c` IS NOT NULL";
            }

            $rows = $conn->fetchAllAssociative(implode(' UNION ', $parts));

            foreach ($rows as $row) {
                $locked[(int) $row['pid']] = true;
            }

            return array_keys($locked);
        }

        // ---------- PostgreSQL ----------
        if ($isPostgres) {
            $sql = "
                SELECT
                  kcu.table_name AS table_name,
                  kcu.column_name AS column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                  ON tc.constraint_name = kcu.constraint_name
                 AND tc.constraint_schema = kcu.constraint_schema
                JOIN information_schema.constraint_column_usage ccu
                  ON ccu.constraint_name = tc.constraint_name
                 AND ccu.constraint_schema = tc.constraint_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND ccu.table_name = :tbl
                  AND ccu.column_name = :pk
                  AND tc.constraint_schema = current_schema()
            ";
            $refs = $conn->fetchAllAssociative($sql, ['tbl' => $produitTable, 'pk' => $pkColumn]);

            if (!$refs) {
                return [];
            }

            $parts = [];
            foreach ($refs as $r) {
                $t = $r['table_name'];
                $c = $r['column_name'];
                $parts[] = "SELECT DISTINCT \"$c\" AS pid FROM \"$t\" WHERE \"$c\" IS NOT NULL";
            }

            $rows = $conn->fetchAllAssociative(implode(' UNION ', $parts));

            foreach ($rows as $row) {
                $locked[(int) $row['pid']] = true;
            }

            return array_keys($locked);
        }

        return [];
    }
}
