<?php

namespace App\Repository;

use App\Entity\Hebergement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hebergement>
 */
class HebergementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hebergement::class);
    }

    public function findDuplicateByName(string $name, ?int $excludeId = null): ?Hebergement
    {
        $queryBuilder = $this->createQueryBuilder('h')
            ->where('TRIM(LOWER(h.nomHebergement)) = :name')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $queryBuilder
                ->andWhere('h.idHebergement != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    //    /**
    //     * @return Hebergement[] Returns an array of Hebergement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Hebergement
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
