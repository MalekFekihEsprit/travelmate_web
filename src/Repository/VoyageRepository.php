<?php

namespace App\Repository;

use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Voyage>
 */
class VoyageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voyage::class);
    }

    /**
     * @param array{search?: string, statut?: string, destination?: int|null, sort?: string} $filters
     *
     * @return Voyage[]
     */
    public function findFilteredVoyages(array $filters): array
    {
        $queryBuilder = $this->createQueryBuilder('v')
            ->leftJoin('v.destination', 'd')
            ->addSelect('d');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(v.titre_voyage) LIKE :search OR LOWER(d.nom_destination) LIKE :search OR LOWER(d.pays_destination) LIKE :search')
                ->setParameter('search', '%'.strtolower($search).'%');
        }

        $statut = trim((string) ($filters['statut'] ?? ''));
        if ($statut !== '') {
            $queryBuilder
                ->andWhere('v.statut = :statut')
                ->setParameter('statut', $statut);
        }

        $destination = $filters['destination'] ?? null;
        if (is_int($destination) && $destination > 0) {
            $queryBuilder
                ->andWhere('d.id_destination = :destination')
                ->setParameter('destination', $destination);
        }

        $this->applySort($queryBuilder, (string) ($filters['sort'] ?? 'date_asc'));

        return $queryBuilder->getQuery()->getResult();
    }

    private function applySort(QueryBuilder $queryBuilder, string $sort): void
    {
        match ($sort) {
            'date_desc' => $queryBuilder->orderBy('v.date_debut', 'DESC')->addOrderBy('v.titre_voyage', 'ASC'),
            'title_asc' => $queryBuilder->orderBy('v.titre_voyage', 'ASC')->addOrderBy('v.date_debut', 'ASC'),
            'title_desc' => $queryBuilder->orderBy('v.titre_voyage', 'DESC')->addOrderBy('v.date_debut', 'ASC'),
            'status_asc' => $queryBuilder->orderBy('v.statut', 'ASC')->addOrderBy('v.date_debut', 'ASC'),
            'destination_asc' => $queryBuilder->orderBy('d.nom_destination', 'ASC')->addOrderBy('v.titre_voyage', 'ASC'),
            default => $queryBuilder->orderBy('v.date_debut', 'ASC')->addOrderBy('v.titre_voyage', 'ASC'),
        };
    }

    //    /**
    //     * @return Voyage[] Returns an array of Voyage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Voyage
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Returns voyages whose date_debut is between tomorrow and $days days from now.
     * Sends a reminder before the trip starts.
     *
     * @return Voyage[]
     */
    public function findVoyagesStartingInDays(int $days = 2): array
    {
        $now     = new \DateTime('today');
        $dayEnd  = (new \DateTime('+' . $days . ' days'))->setTime(23, 59, 59);

        return $this->createQueryBuilder('v')
            ->where('v.date_debut BETWEEN :now AND :end')
            ->andWhere('LOWER(v.statut) NOT LIKE :cancelled')
            ->setParameter('now', $now)
            ->setParameter('end', $dayEnd)
            ->setParameter('cancelled', '%annul%')
            ->orderBy('v.date_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
