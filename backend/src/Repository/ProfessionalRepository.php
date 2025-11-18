<?php

namespace App\Repository;

use App\Entity\Professional;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Professional>
 */
class ProfessionalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Professional::class);
    }


    public function searchProfessionals(
        ?string $speciality,
        ?string $zone,
        ?string $query,
        ?float $lat,
        ?float $lng
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        // Distance Haversine ou valeur très haute si pas de coords
        $distanceSql = ($lat !== null && $lng !== null)
            ? "(6371 * acos(
                cos(radians(:lat)) 
                * cos(radians(p.latitude)) 
                * cos(radians(p.longitude) - radians(:lng)) 
                + sin(radians(:lat)) 
                * sin(radians(p.latitude))
            ))"
            : "99999";

        $sql = "
        SELECT 
            p.*,
            $distanceSql AS distance,
            s.name AS speciality
        FROM professional p
        INNER JOIN speciality s ON p.speciality_id = s.id
        WHERE p.availability = 1
    ";

        $params = [];
        $types = [];

        if ($speciality) {
            $sql .= " AND s.name LIKE :spec";
            $params['spec'] = "%$speciality%";
        }

        if ($zone) {
            $sql .= " AND LOWER(p.zone) LIKE LOWER(:zone)";
            $params['zone'] = "%$zone%";
        }

        if ($query) {
            $sql .= "
            AND (
                p.full_name LIKE :q
                OR p.description LIKE :q
                OR p.company_name LIKE :q
            )
        ";
            $params['q'] = "%$query%";
        }

        // Ajout des paramètres lat/lng AVANT exécution
        if ($lat !== null && $lng !== null) {
            $params['lat'] = $lat;
            $params['lng'] = $lng;
            $sql .= " ORDER BY distance ASC";
        } else {
            $sql .= " ORDER BY p.price_per_hour ASC";
        }

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);

        return $result->fetchAllAssociative();
    }



    //    /**
    //     * @return Professional[] Returns an array of Professional objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Professional
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
