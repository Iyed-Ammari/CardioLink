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
     * @return array<int, array<string, mixed>>
     */
    public function findReactionsSummary(Message $message): array
    {
        return $this->createQueryBuilder('mr')
            ->select('mr.emoji, COUNT(mr.id) as count')
            ->where('mr.message = :message')
            ->setParameter('message', $message)
            ->groupBy('mr.emoji')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
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