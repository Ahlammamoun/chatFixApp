<?php

namespace App\Repository;

use App\Entity\Offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offer>
 */
class OfferRepository extends ServiceEntityRepository
{
    public const STATUS_PAID = 'paid';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /**
     * CA total (offres payées)
     */
    public function getTotalRevenue(): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.price), 0)')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->setParameter('paid', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de transactions payées
     */
    public function getPaidCount(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->setParameter('paid', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Panier moyen (moyenne des offres payées)
     */
    public function getAverageBasket(): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(AVG(o.price), 0)')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->setParameter('paid', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * CA sur une période [start, end)
     */
    public function getRevenueBetween(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.price), 0)')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->andWhere('o.paidAt >= :start')
            ->andWhere('o.paidAt < :end')
            ->setParameter('paid', self::STATUS_PAID)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * CA du mois courant
     */
    public function getRevenueThisMonth(): float
    {
        $start = new \DateTimeImmutable('first day of this month 00:00:00');
        $end = $start->modify('+1 month');

        return $this->getRevenueBetween($start, $end);
    }

    /**
     * CA des 30 derniers jours
     */
    public function getRevenueLast30Days(): float
    {
        $end = new \DateTimeImmutable('now');
        $start = $end->modify('-30 days');

        return $this->getRevenueBetween($start, $end);
    }

    /**
     * CA du jour (aujourd’hui)
     */
    public function getRevenueToday(): float
    {
        $start = new \DateTimeImmutable('today 00:00:00');
        $end = $start->modify('+1 day');

        return $this->getRevenueBetween($start, $end);
    }

    /**
     * CA par professionnel (trié décroissant)
     * Retour: [['professionalId'=>1,'fullName'=>'...','revenue'=>123.45,'count'=>10], ...]
     */
    public function getRevenueByProfessional(): array
    {
        return $this->createQueryBuilder('o')
            ->select('p.id AS professionalId')
            ->addSelect('p.fullName AS fullName')
            ->addSelect('COALESCE(SUM(o.price), 0) AS revenue')
            ->addSelect('COUNT(o.id) AS count')
            ->join('o.professional', 'p')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->setParameter('paid', self::STATUS_PAID)
            ->groupBy('p.id')
            ->orderBy('revenue', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * CA d’un professionnel
     */
    public function getRevenueForProfessional(int $professionalId): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.price), 0)')
            ->join('o.professional', 'p')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->andWhere('p.id = :pid')
            ->setParameter('paid', self::STATUS_PAID)
            ->setParameter('pid', $professionalId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * CA d’un professionnel sur période [start, end)
     */
    public function getRevenueForProfessionalBetween(
        int $professionalId,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): float {
        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.price), 0)')
            ->join('o.professional', 'p')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->andWhere('p.id = :pid')
            ->andWhere('o.paidAt >= :start')
            ->andWhere('o.paidAt < :end')
            ->setParameter('paid', self::STATUS_PAID)
            ->setParameter('pid', $professionalId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top pros par CA
     */
    public function getTopProfessionalsByRevenue(int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->select('p.id AS professionalId')
            ->addSelect('p.fullName AS fullName')
            ->addSelect('COALESCE(SUM(o.price), 0) AS revenue')
            ->join('o.professional', 'p')
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->setParameter('paid', self::STATUS_PAID)
            ->groupBy('p.id')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Série CA par jour (pour graphique)
     * Retour: [['day'=>'2026-01-28','revenue'=>123.45,'count'=>2], ...]
     */
    public function getDailyRevenue(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        // MySQL/MariaDB : DATE(o.paidAt)
        return $this->createQueryBuilder('o')
            ->select("DATE(o.paidAt) AS day")
            ->addSelect("COALESCE(SUM(o.price), 0) AS revenue")
            ->addSelect("COUNT(o.id) AS count")
            ->andWhere('o.status = :paid')
            ->andWhere('o.paidAt IS NOT NULL')
            ->andWhere('o.paidAt >= :start')
            ->andWhere('o.paidAt < :end')
            ->setParameter('paid', self::STATUS_PAID)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
