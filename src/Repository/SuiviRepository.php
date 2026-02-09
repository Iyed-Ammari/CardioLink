<?php

namespace App\Repository;

use App\Entity\Suivi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Suivi>
 */
class SuiviRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suivi::class);
    }

    /**
     * Récupère tous les suivis d'un patient
     */
    public function findByPatient($patientId)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.patient = :patientId')
            ->setParameter('patientId', $patientId)
            ->orderBy('s.dateSaisie', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les suivis critiques récents
     */
    public function findCriticalRecent($limit = 10)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.niveauUrgence = :urgence')
            ->setParameter('urgence', 'Critique')
            ->orderBy('s.dateSaisie', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le dernier suivi d'un patient pour un type de donnée
     */
    public function findLastByPatientAndType($patientId, $typeDonnee)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.patient = :patientId')
            ->andWhere('s.typeDonnee = :typeDonnee')
            ->setParameter('patientId', $patientId)
            ->setParameter('typeDonnee', $typeDonnee)
            ->orderBy('s.dateSaisie', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
