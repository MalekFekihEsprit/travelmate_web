<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    /**
     * @param Voyage[] $voyages
     *
     * @return array<int, array{totalAmount: float, currency: string|null, currencyCount: int}>
     */
    public function findVoyageBudgetSummaries(array $voyages): array
    {
        $voyages = array_values(array_filter(
            $voyages,
            static fn (mixed $voyage): bool => $voyage instanceof Voyage && $voyage->getIdVoyage() !== null
        ));

        if ($voyages === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('budget')
            ->select('IDENTITY(budget.voyage) AS voyageId')
            ->addSelect('SUM(budget.montant_total) AS totalAmount')
            ->addSelect('MIN(budget.devise_budget) AS currency')
            ->addSelect('COUNT(DISTINCT budget.devise_budget) AS currencyCount')
            ->andWhere('budget.voyage IN (:voyages)')
            ->setParameter('voyages', $voyages)
            ->groupBy('budget.voyage')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];

        foreach ($rows as $row) {
            $voyageId = (int) ($row['voyageId'] ?? 0);

            if ($voyageId <= 0) {
                continue;
            }

            $currency = isset($row['currency']) && is_string($row['currency']) && trim($row['currency']) !== ''
                ? trim($row['currency'])
                : null;

            $summaries[$voyageId] = [
                'totalAmount' => (float) ($row['totalAmount'] ?? 0),
                'currency' => $currency,
                'currencyCount' => (int) ($row['currencyCount'] ?? 0),
            ];
        }

        return $summaries;
    }

    //    /**
    //     * @return Budget[] Returns an array of Budget objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Budget
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
