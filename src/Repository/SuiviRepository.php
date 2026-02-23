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
     * Récupère les suivis d'un patient avec filtres de recherche et de tri
     * Utilisé dans SuiviController::index
     */
    public function findByPatientFiltered(int $patientId, ?string $search = null, string $sort = 'dateSaisie', string $direction = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.patient = :patientId')
            ->setParameter('patientId', $patientId);

        // --- LOGIQUE DE RECHERCHE ---
        if ($search) {
            $qb->andWhere('s.typeDonnee LIKE :search OR s.niveauUrgence LIKE :search OR s.valeur LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // --- LOGIQUE DE TRI SÉCURISÉE ---
        // Liste blanche des colonnes autorisées pour éviter l'injection SQL
        $allowedSorts = ['id', 'typeDonnee', 'valeur', 'dateSaisie', 'niveauUrgence'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'dateSaisie';
        
        // Sécurisation de la direction (ASC ou DESC uniquement)
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('s.' . $sort, $direction);

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère tous les suivis d'un patient (version simple sans filtre)
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