<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageReaction>
 */
class MessageReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageReaction::class);
    }

    /**
     * Récupère une réaction spécifique
     */
    public function findReaction(Message $message, User $user, string $emoji): ?MessageReaction
    {
        return $this->createQueryBuilder('mr')
            ->where('mr.message = :message')
            ->andWhere('mr.user = :user')
            ->andWhere('mr.emoji = :emoji')
            ->setParameter('message', $message)
            ->setParameter('user', $user)
            ->setParameter('emoji', $emoji)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les réactions d'un message groupées par emoji avec comptage
     */
    public function findReactionsSummary(Message $message): array
    {
        $results = $this->createQueryBuilder('mr')
            ->select('mr.emoji, COUNT(mr.id) as count')
            ->where('mr.message = :message')
            ->setParameter('message', $message)
            ->groupBy('mr.emoji')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Récupère les utilisateurs qui ont réagi avec un emoji spécifique
     */
    public function findUsersByEmoji(Message $message, string $emoji): array
    {
        return $this->createQueryBuilder('mr')
            ->select('mr.user')
            ->where('mr.message = :message')
            ->andWhere('mr.emoji = :emoji')
            ->setParameter('message', $message)
            ->setParameter('emoji', $emoji)
            ->getQuery()
            ->getResult();
    }
}
