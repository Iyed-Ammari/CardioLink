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
// src/Repository/RendezVousRepository.php

/**
 * Vérifie si un créneau est disponible pour un médecin
 * 
 * @param \DateTime $dateHeure La date/heure du rendez-vous à vérifier
 * @param User $medecin Le médecin concerné
 * @param ?int $excludeId L'ID du RDV à exclure (pour les modifications)
 * @return int Le nombre de RDV en conflit
 */
public function countCrenau(\DateTime $dateHeure, User $medecin, ?int $excludeId = null): int
{
    // On suppose qu'un RDV dure 30 min
    $fin = (clone $dateHeure)->add(new \DateInterval('PT30M'));

    $qb = $this->createQueryBuilder('r')
        ->select('r')
        ->where('r.medecin = :medecin')
        // Le RDV commence avant la fin du nouveau
        ->andWhere('r.dateHeure < :fin')
        // Et finit après le début du nouveau (en supposant 30 min de durée)
        ->andWhere('r.dateHeure + 30 * 60 > :debut')
        ->setParameter('medecin', $medecin)
        ->setParameter('debut', $dateHeure)
        ->setParameter('fin', $fin);

    // Exclure le RDV actuel si on modifie
    if ($excludeId !== null) {
        $qb->andWhere('r.id != :excludeId')
            ->setParameter('excludeId', $excludeId);
    }

    return count($qb->getQuery()->getResult());
}
    //    /**
    //     * @return RendezVous[] Returns an array of RendezVous objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?RendezVous
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
