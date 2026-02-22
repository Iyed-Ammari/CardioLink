<?php

namespace App\Repository;

use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    /**
     * Vérifie la disponibilité d'un créneau pour un médecin
     */
    public function countCrenau(\DateTime $debut, User $medecin, ?int $excludeId = null): int
    {
        // On suppose qu'un RDV dure 30 min
        $fin = (clone $debut)->modify('+30 minutes');

        $qb = $this->createQueryBuilder('r')
            ->select('count(r.id)')
            ->where('r.medecin = :medecin')
            ->andWhere('r.dateHeure < :fin')
            ->andWhere('DATE_ADD(r.dateHeure, 30, \'minute\') > :debut')
            ->setParameter('medecin', $medecin)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Recherche globale avec filtrage par rôle et tris dynamiques
     */
    public function searchGlobal(
        ?string $search,
        ?string $sortField,
        ?string $sortOrder,
        User $user,
        string $role
    ) {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p') // On lie la table User pour le patient
            ->leftJoin('r.medecin', 'm')  // On lie la table User pour le médecin
            ->addSelect('p', 'm');

        // Sécurité : filtrage selon l'utilisateur connecté 
        if (str_contains($role, 'ROLE_MEDECIN')) {
            $qb->andWhere('r.medecin = :user');
        } else {
            $qb->andWhere('r.patient = :user');
        }
        $qb->setParameter('user', $user);

        // Recherche textuelle
        // ================= RECHERCHE =================
        if (!empty($search)) {
            // On ne force PAS le minuscule ici pour laisser la DB gérer
            $searchTerm = '%' . trim($search) . '%';

            if ($role === 'ROLE_MEDECIN') {
                // Le médecin cherche un PATIENT : on concatène Nom + Prénom
                $qb->andWhere(
                    $qb->expr()->orX(
                        "CONCAT(p.nom, ' ', p.prenom) LIKE :search",
                        "CONCAT(p.prenom, ' ', p.nom) LIKE :search",
                        "r.statut LIKE :search",
                        "r.type LIKE :search"
                    )
                );
            } else {
                // Le patient cherche un MÉDECIN : on concatène Nom + Prénom
                $qb->andWhere(
                    $qb->expr()->orX(
                        "CONCAT(m.nom, ' ', m.prenom) LIKE :search",
                        "CONCAT(m.prenom, ' ', m.nom) LIKE :search",
                        "r.statut LIKE :search",
                        "r.type LIKE :search"
                    )
                );
            }

            $qb->setParameter('search', $searchTerm);
        }

        // Gestion du Tri [cite: 11, 14, 17]
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        if ($sortField === 'patient') {
            $qb->orderBy('p.nom', $sortOrder);
        } elseif ($sortField === 'medecin') {
            $qb->orderBy('m.nom', $sortOrder);
        } else {
            $qb->orderBy('r.dateHeure', $sortOrder);
        }

        return $qb->getQuery()->getResult();
    }
    public function countByMonthAndMedecin(int $medecinId, int $mois, int $annee): int
    {
        $startDate = new \DateTime("$annee-$mois-01 00:00:00");
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.medecin = :medecin')
            ->andWhere('r.dateHeure BETWEEN :start AND :end')
            ->setParameter('medecin', $medecinId)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
    public function getStatsMensuellesMedecin(int $medecinId): array
    {
        return $this->createQueryBuilder('r')
            ->select("SUBSTRING(r.dateHeure, 1, 7) as mois, COUNT(r.id) as total")
            ->where('r.medecin = :id')
            ->setParameter('id', $medecinId)
            ->groupBy('mois')
            ->orderBy('mois', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
