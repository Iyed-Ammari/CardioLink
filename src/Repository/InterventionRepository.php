<?php

namespace App\Repository;

use App\Entity\Intervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Intervention>
 */
class InterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervention::class);
    }

    /**
     * Recherche et tri des interventions en attente
     */
    public function findBySearch(?string $term, string $direction = 'ASC')
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.statut = :statut')
            ->setParameter('statut', 'En attente');

        if ($term) {
            $qb->andWhere('i.type LIKE :term OR i.description LIKE :term')
               ->setParameter('term', '%' . $term . '%');
        }

        $qb->orderBy('i.datePlanifiee', $direction);

        return $qb->getQuery()->getResult();
    }

    public function findPending()
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.statut = :statut')
            ->setParameter('statut', 'En attente')
            ->orderBy('i.datePlanifiee', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUrgentSOS()
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.type = :type')
            ->andWhere('i.statut IN (:statuts)')
            ->setParameter('type', 'Alerte SOS')
            ->setParameter('statuts', ['En attente', 'Acceptée'])
            ->orderBy('i.datePlanifiee', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByMedecin($medecinId)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.medecin = :medecinId')
            ->setParameter('medecinId', $medecinId)
            ->orderBy('i.datePlanifiee', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBySuiviOrigine($suiviId)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.suiviOrigine = :suiviId')
            ->setParameter('suiviId', $suiviId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}