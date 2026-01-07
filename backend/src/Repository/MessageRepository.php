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
    public function findConversation(int $userId, int $professionalUserId): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :user AND m.receiver = :pro) OR (m.sender = :pro AND m.receiver = :user)')
            ->setParameter('user', $userId)
            ->setParameter('pro', $professionalUserId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Marque comme lus uniquement les messages reçus par receiver depuis sender
     * (ne touche pas aux messages dans l’autre sens)
     */
    public function markAsRead(int $receiverId, int $senderId): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', ':read')
            ->where('m.receiver = :receiver')
            ->andWhere('m.sender = :sender')
            ->andWhere('(m.isRead = false OR m.isRead IS NULL)')
            ->setParameter('read', true)
            ->setParameter('receiver', $receiverId)
            ->setParameter('sender', $senderId)
            ->getQuery()
            ->execute();
    }

    /**
     * ✅ Inbox PRO : 1 ligne par client
     * Retour attendu :
     * [
     *   { clientId, clientName, lastMessage, unreadCount }
     * ]
     */
    public function findProThreads(int $proUserId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // On part des clients (`user`) et on vérifie qu'il existe au moins 1 message
        // entre le pro (proUserId) et ce client.
        // Puis on calcule lastMessage + unreadCount via sous-requêtes.
        $sql = "
            SELECT
                c.id AS clientId,
                TRIM(CONCAT(COALESCE(c.name,''), ' ', COALESCE(c.lastname,''))) AS clientName,
                (
                    SELECT m2.content
                    FROM message m2
                    WHERE
                        (m2.sender_id = :proUserId AND m2.receiver_id = c.id)
                        OR
                        (m2.sender_id = c.id AND m2.receiver_id = :proUserId)
                    ORDER BY m2.created_at DESC
                    LIMIT 1
                ) AS lastMessage,
                (
                    SELECT COUNT(*)
                    FROM message m3
                    WHERE m3.sender_id = c.id
                      AND m3.receiver_id = :proUserId
                      AND (m3.is_read = 0 OR m3.is_read IS NULL)
                ) AS unreadCount,
                (
                    SELECT m4.created_at
                    FROM message m4
                    WHERE
                        (m4.sender_id = :proUserId AND m4.receiver_id = c.id)
                        OR
                        (m4.sender_id = c.id AND m4.receiver_id = :proUserId)
                    ORDER BY m4.created_at DESC
                    LIMIT 1
                ) AS lastAt
            FROM `user` c
            WHERE c.id <> :proUserId
              AND EXISTS (
                SELECT 1
                FROM message m
                WHERE
                    (m.sender_id = :proUserId AND m.receiver_id = c.id)
                    OR
                    (m.sender_id = c.id AND m.receiver_id = :proUserId)
              )
            ORDER BY lastAt DESC
        ";

        $rows = $conn->executeQuery($sql, ['proUserId' => $proUserId])->fetchAllAssociative();

        // Normalisation types + fallback nom
        return array_map(static function ($r) {
            $name = trim((string)($r['clientName'] ?? ''));
            if ($name === '') $name = 'Client';

            return [
                'clientId'     => (int)($r['clientId'] ?? 0),
                'clientName'   => $name,
                'lastMessage'  => (string)($r['lastMessage'] ?? ''),
                'unreadCount'  => (int)($r['unreadCount'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * ✅ Inbox USER : 1 ligne par professionnel
     * Retour attendu :
     * [
     *   { professionalId, professionalName, lastMessage, unreadCount }
     * ]
     */
    public function findUserThreads(int $userId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT
                p.id AS professionalId,
                p.full_name AS professionalName,
                (
                    SELECT m2.content
                    FROM message m2
                    WHERE
                        (m2.sender_id = :userId AND m2.receiver_id = p.user_id)
                        OR
                        (m2.sender_id = p.user_id AND m2.receiver_id = :userId)
                    ORDER BY m2.created_at DESC
                    LIMIT 1
                ) AS lastMessage,
                (
                    SELECT COUNT(*)
                    FROM message m3
                    WHERE m3.sender_id = p.user_id
                      AND m3.receiver_id = :userId
                      AND (m3.is_read = 0 OR m3.is_read IS NULL)
                ) AS unreadCount,
                (
                    SELECT m4.created_at
                    FROM message m4
                    WHERE
                        (m4.sender_id = :userId AND m4.receiver_id = p.user_id)
                        OR
                        (m4.sender_id = p.user_id AND m4.receiver_id = :userId)
                    ORDER BY m4.created_at DESC
                    LIMIT 1
                ) AS lastAt
            FROM professional p
            WHERE EXISTS (
                SELECT 1
                FROM message m
                WHERE
                    (m.sender_id = :userId AND m.receiver_id = p.user_id)
                    OR
                    (m.sender_id = p.user_id AND m.receiver_id = :userId)
            )
            ORDER BY lastAt DESC
        ";

        $rows = $conn->executeQuery($sql, ['userId' => $userId])->fetchAllAssociative();

        return array_map(static function ($row) {
            return [
                'professionalId'   => (int)($row['professionalId'] ?? 0),
                'professionalName' => (string)($row['professionalName'] ?? 'Professionnel'),
                'lastMessage'      => (string)($row['lastMessage'] ?? ''),
                'unreadCount'      => (int)($row['unreadCount'] ?? 0),
            ];
        }, $rows);
    }
}
