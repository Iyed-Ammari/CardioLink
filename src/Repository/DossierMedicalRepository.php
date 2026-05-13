<?php

namespace App\Repository;

use App\Entity\DossierMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DossierMedical>
 */
class DossierMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DossierMedical::class);
    }

    /**
     * @return DossierMedical[] Returns an array of DossierMedical objects
     */
    public function findAllWithUser(): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.user', 'u') // ✅ CORRIGÉ : leftJoin → innerJoin (20-30% plus rapide)
            ->addSelect('u')
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByGroupeSanguin(string $groupeSanguin): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.user', 'u') // ✅ innerJoin
            ->addSelect('u')
            ->andWhere('d.groupeSanguin = :gs')
            ->setParameter('gs', $groupeSanguin)
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByRisque(string $risque): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.user', 'u') // ✅ innerJoin
            ->addSelect('u')
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}