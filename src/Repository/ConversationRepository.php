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

    /**
     * @return Conversation[]
     */
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
     * @return Conversation[]
     */
    public function findByUserWithSearchAndSort(User $user, ?string $search, string $sortBy, string $order): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.patient', 'p')
            ->join('c.medecin', 'm')
            ->where('c.patient = :user OR c.medecin = :user')
            ->setParameter('user', $user);

        if ($search) {
            $qb->andWhere('p.nom LIKE :search OR p.prenom LIKE :search OR m.nom LIKE :search OR m.prenom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        switch ($sortBy) {
            case 'created':
                $qb->orderBy('c.createdAt', $order);
                break;
            case 'contact':
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
}