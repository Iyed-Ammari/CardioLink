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
     * Récupère toutes les interventions en attente
     */
    public function findPending()
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.statut = :statut')
            ->setParameter('statut', 'En attente')
            ->orderBy('i.datePlanifiee', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les alertes SOS urgentes
     */
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

    /**
     * Récupère les interventions d'un médecin
     */
    public function findByMedecin($medecinId)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.medecin = :medecinId')
            ->setParameter('medecinId', $medecinId)
            ->orderBy('i.datePlanifiee', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère l'intervention associée à un suivi
     */
    public function findBySuiviOrigine($suiviId)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.suiviOrigine = :suiviId')
            ->setParameter('suiviId', $suiviId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
