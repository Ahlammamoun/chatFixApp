<?php

namespace App\Repository;

use App\Entity\SupportTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SupportTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportTicket::class);
    }

    /**
     * ✅ Créer un ticket support
     */
    public function create(string $email, string $subject, string $message): SupportTicket
    {
        $ticket = new SupportTicket();
        $ticket
            ->setEmail($email)
            ->setSubject($subject)
            ->setMessage($message);

        $em = $this->getEntityManager(); // ✅ au lieu de $_em
        $em->persist($ticket);
        $em->flush();

        return $ticket;
    }

    /**
     * ✅ Derniers tickets
     */
    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Tickets par email
     */
    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.email = :email')
            ->setParameter('email', $email)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
