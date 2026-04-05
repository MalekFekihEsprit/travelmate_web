<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function searchForAdmin(?string $term, ?string $role = null, ?string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($term && trim($term) !== '') {
            $term = '%'.mb_strtolower(trim($term)).'%';

            $qb
                ->andWhere('LOWER(u.nom) LIKE :term OR LOWER(u.prenom) LIKE :term OR LOWER(u.email) LIKE :term')
                ->setParameter('term', $term);
        }

        if ($role && in_array($role, ['ADMIN', 'USER'], true)) {
            $qb->andWhere('u.role = :role')
            ->setParameter('role', $role);
        }

        switch ($sort) {
            case 'oldest':
                $qb->orderBy('u.created_at', 'ASC');
                break;
            case 'name_asc':
                $qb->orderBy('u.nom', 'ASC')
                ->addOrderBy('u.prenom', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('u.nom', 'DESC')
                ->addOrderBy('u.prenom', 'DESC');
                break;
            case 'newest':
            default:
                $qb->orderBy('u.created_at', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.role = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getRegistrationsByDay(int $days = 7): array
    {
        $startDate = new \DateTimeImmutable(sprintf('-%d days', $days - 1));
        $startDate = $startDate->setTime(0, 0, 0);

        $results = $this->createQueryBuilder('u')
            ->select('u.created_at')
            ->andWhere('u.created_at >= :startDate')
            ->setParameter('startDate', $startDate)
            ->orderBy('u.created_at', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->modify("+$i day")->format('Y-m-d');
            $counts[$date] = 0;
        }

        foreach ($results as $row) {
            $created_at = $row['created_at'] ?? null;

            if ($created_at instanceof \DateTimeInterface) {
                $key = $created_at->format('Y-m-d');
                if (array_key_exists($key, $counts)) {
                    $counts[$key]++;
                }
            }
        }

        return $counts;
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
