<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * 🔥 Récupère la conversation entre 2 utilisateurs
     */
    public function findConversation(int $userId, int $professionalId): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :user AND m.receiver = :pro) OR (m.sender = :pro AND m.receiver = :user)')
            ->setParameter('user', $userId)
            ->setParameter('pro', $professionalId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function markAsRead(int $receiverId, int $senderId): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', ':read')
            ->where('m.receiver = :receiver')
            ->andWhere('m.sender = :sender')
            ->andWhere('m.isRead = false')
            ->setParameter('read', true)
            ->setParameter('receiver', $receiverId)
            ->setParameter('sender', $senderId)
            ->getQuery()
            ->execute();
    }

    public function findProThreads(int $proUserId): array
    {
        // On récupère le dernier message par "client"
        // pour toutes les conversations où le pro est receiver OU sender.

        $conn = $this->getEntityManager()->getConnection();

        $sql = "
    SELECT
        u.id AS clientId,
        CONCAT(COALESCE(u.name,''), ' ', COALESCE(u.lastname,'')) AS clientName,
        m.content AS lastMessage,
        UNIX_TIMESTAMP(m.created_at) AS lastAt,
        (
            SELECT COUNT(*)
            FROM message m2
            WHERE m2.receiver_id = :proUserId
              AND m2.sender_id = u.id
              AND m2.is_read = 0
        ) AS unreadCount
    FROM message m
    JOIN user u ON (
        CASE
          WHEN m.sender_id = :proUserId THEN m.receiver_id
          ELSE m.sender_id
        END
    ) = u.id
    WHERE (m.sender_id = :proUserId OR m.receiver_id = :proUserId)   -- ✅ parenthèses
      AND u.id <> :proUserId
      AND m.id = (
          SELECT m3.id
          FROM message m3
          WHERE (m3.sender_id = :proUserId AND m3.receiver_id = u.id)
             OR (m3.sender_id = u.id AND m3.receiver_id = :proUserId)
          ORDER BY m3.created_at DESC
          LIMIT 1
      )
    ORDER BY m.created_at DESC
";


        return $conn->executeQuery($sql, ['proUserId' => $proUserId])->fetchAllAssociative();
    }
}
