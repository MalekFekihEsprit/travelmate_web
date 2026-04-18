<?php

namespace App\Repository;

use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    /**
     * Charge une catégorie ET toutes ses activités en une seule requête SQL (LEFT JOIN).
     * Évite le problème N+1 queries.
     */
    public function findWithActivites(int $id): ?Categorie
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.activites', 'a')
            ->addSelect('a')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
