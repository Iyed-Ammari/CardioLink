<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(printf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Récupère tous les utilisateurs avec le rôle ROLE_MEDECIN
     * @return User[]
     */
    public function findMedecins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_MEDECIN%')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function countSignupsByDay(): array
    {
      $conn = $this->getEntityManager()->getConnection();

      $sql = "
        SELECT 
            DATE(created_at) AS date,
            COUNT(id) AS total
        FROM `user`
        WHERE created_at IS NOT NULL
        GROUP BY DATE(created_at)
        ORDER BY date ASC
      ";

     return $conn->executeQuery($sql)->fetchAllAssociative();
    }  


    /**
     * Récupère tous les utilisateurs avec le rôle ROLE_PATIENT
     * @return User[]
     */
    public function findPatients(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_PATIENT%')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function countPatients(): int
{
    return $this->createQueryBuilder('u')
        ->select('COUNT(u.id)')
        ->where('u.roles LIKE :role')
        ->setParameter('role', '%ROLE_PATIENT%')
        ->getQuery()
        ->getSingleScalarResult();
}

public function countMedecins(): int
{
    return $this->createQueryBuilder('u')
        ->select('COUNT(u.id)')
        ->where('u.roles LIKE :role')
        ->setParameter('role', '%ROLE_MEDECIN%')
        ->getQuery()
        ->getSingleScalarResult();
}

public function countInscriptionsMois(): int
{
    $debut = new \DateTime('first day of this month');
    $fin = new \DateTime('last day of this month');

    return $this->createQueryBuilder('u')
        ->select('COUNT(u.id)')
        ->where('u.createdAt BETWEEN :debut AND :fin')
        ->setParameter('debut', $debut)
        ->setParameter('fin', $fin)
        ->getQuery()
        ->getSingleScalarResult();
}

}
