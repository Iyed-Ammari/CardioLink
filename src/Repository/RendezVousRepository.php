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

public function countCrenau(\DateTime $debut, User $medecin, ?int $excludeId = null): int
{
    // On suppose qu'un RDV dure 30 min
    $fin = (clone $debut)->modify('+30 minutes');

    $qb = $this->createQueryBuilder('r')
        ->select('count(r.id)')
        ->where('r.medecin = :medecin')
        ->andWhere('r.dateHeure < :fin') // Le RDV commence avant la fin du nouveau
        ->andWhere('DATE_ADD(r.dateHeure, 30, \'minute\') > :debut') // Et finit après le début du nouveau
        ->setParameter('medecin', $medecin)
        ->setParameter('debut', $debut)
        ->setParameter('fin', $fin);
    
    // Si on modifie un RDV existant, on l'exclut de la vérification
    if ($excludeId !== null) {
        $qb->andWhere('r.id != :excludeId')
            ->setParameter('excludeId', $excludeId);
    }
    
    return $qb->getQuery()
        ->getSingleScalarResult();
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
