<?php

namespace App\Repository;

use App\Entity\Destination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Destination>
 */
class DestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }

    public function findDuplicateByName(string $name, ?int $excludeId = null): ?Destination
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->where('TRIM(LOWER(d.nom_destination)) = :name')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $queryBuilder
                ->andWhere('d.id_destination != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    //    /**
    //     * @return Destination[] Returns an array of Destination objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Destination
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
