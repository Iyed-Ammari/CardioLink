<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.patient = :user OR c.medecin = :user')
            ->setParameter('user', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPatientAndMedecin(User $patient, User $medecin): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->where('(c.patient = :patient AND c.medecin = :medecin) OR (c.patient = :medecin AND c.medecin = :patient)')
            ->setParameter('patient', $patient)
            ->setParameter('medecin', $medecin)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Cherche et trie les conversations d'un utilisateur
     * 
     * @param User $user L'utilisateur
     * @param string|null $search Texte à rechercher
     * @param string $sortBy Colonne à trier ('updated', 'created', 'contact', 'status')
     * @param string $order Ordre du tri ('ASC' ou 'DESC')
     * @return Conversation[]
     */
    public function findByUserWithSearchAndSort(User $user, ?string $search = null, string $sortBy = 'updated', string $order = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')
            ->leftJoin('c.medecin', 'm')
            ->where('c.patient = :user OR c.medecin = :user')
            ->setParameter('user', $user);

        // Ajout du filtre de recherche
        if ($search) {
            $searchTerm = '%' . $search . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('p.prenom', ':search'),
                    $qb->expr()->like('p.nom', ':search'),
                    $qb->expr()->like('m.prenom', ':search'),
                    $qb->expr()->like('m.nom', ':search'),
                    $qb->expr()->like('p.email', ':search'),
                    $qb->expr()->like('m.email', ':search')
                )
            )
            ->setParameter('search', $searchTerm);
        }

        // Tri
        switch ($sortBy) {
            case 'created':
                $qb->orderBy('c.createdAt', $order);
                break;
            case 'contact':
                // Tri par le nom du contact (patient ou médecin selon l'utilisateur)
                $qb->addOrderBy('p.nom', $order)
                   ->addOrderBy('m.nom', $order);
                break;
            case 'status':
                $qb->orderBy('c.isActive', $order)
                   ->addOrderBy('c.updatedAt', 'DESC');
                break;
            case 'updated':
            default:
                $qb->orderBy('c.updatedAt', $order);
                break;
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Conversation[] Returns an array of Conversation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Conversation
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
