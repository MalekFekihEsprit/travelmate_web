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

    public function searchForAdmin(?string $term, ?string $role = null): array
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

    public function countVerifiedUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.is_verified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnverifiedUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.is_verified = :verified')
            ->setParameter('verified', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUsersLastDays(int $days = 30): int
    {
        $since = new \DateTimeImmutable("-$days days");
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.last_login >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getRegistrationsByDayExtended(int $days = 30): array
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

    public function getRegistrationGrowth(): array
    {
        $last30 = $this->getRegistrationsByDayExtended(30);
        $previous30 = $this->getRegistrationsByDayExtended(60, 30); // offset 30 days back
        return [
            'last30Total' => array_sum($last30),
            'previous30Total' => array_sum($previous30),
            'percentageChange' => $this->calculatePercentageChange(array_sum($previous30), array_sum($last30))
        ];
    }

    private function calculatePercentageChange($old, $new): float
    {
        if ($old == 0) return $new > 0 ? 100 : 0;
        return round(($new - $old) / $old * 100, 1);
    }

    // src/Repository/UserRepository.php

    /**
     * Get age distribution statistics
     * Returns count of users in different age groups
     */
    public function getAgeDistribution(): array
    {
        $users = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->getQuery()
            ->getResult();

        $distribution = [
            '18-24' => 0,
            '25-34' => 0,
            '35-44' => 0,
            '45-54' => 0,
            '55+' => 0,
            'unknown' => 0,
        ];

        $now = new \DateTime();

        foreach ($users as $user) {
            $birthdate = $user['date_naissance'] ?? $user->getDateNaissance();
            
            if (!$birthdate) {
                $distribution['unknown']++;
                continue;
            }

            $age = $birthdate->diff($now)->y;

            if ($age < 18) {
                // Optional: under 18 category
                $distribution['under-18'] = ($distribution['under-18'] ?? 0) + 1;
            } elseif ($age <= 24) {
                $distribution['18-24']++;
            } elseif ($age <= 34) {
                $distribution['25-34']++;
            } elseif ($age <= 44) {
                $distribution['35-44']++;
            } elseif ($age <= 54) {
                $distribution['45-54']++;
            } else {
                $distribution['55+']++;
            }
        }

        return $distribution;
    }

    /**
     * Get average age of users
     */
    public function getAverageAge(): float
    {
        $users = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            return 0;
        }

        $totalAge = 0;
        $count = 0;
        $now = new \DateTime();

        foreach ($users as $user) {
            $birthdate = $user['date_naissance'] ?? $user->getDateNaissance();
            if ($birthdate) {
                $totalAge += $birthdate->diff($now)->y;
                $count++;
            }
        }

        return $count > 0 ? round($totalAge / $count, 1) : 0;
    }

    /**
     * Get youngest user's age
     */
    public function getYoungestAge(): ?int
    {
        $result = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->orderBy('u.date_naissance', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result || !($birthdate = $result['date_naissance'] ?? null)) {
            return null;
        }

        return $birthdate->diff(new \DateTime())->y;
    }

    /**
     * Get oldest user's age
     */
    public function getOldestAge(): ?int
    {
        $result = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->orderBy('u.date_naissance', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result || !($birthdate = $result['date_naissance'] ?? null)) {
            return null;
        }

        return $birthdate->diff(new \DateTime())->y;
    }

    /**
     * Get users by birth year (for trend analysis)
     */
    public function getUsersByBirthYear(): array
    {
        $users = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->getQuery()
            ->getResult();

        $birthYears = [];

        foreach ($users as $user) {
            $birthdate = $user['date_naissance'] ?? $user->getDateNaissance();
            if ($birthdate) {
                $year = $birthdate->format('Y');
                if (!isset($birthYears[$year])) {
                    $birthYears[$year] = 0;
                }
                $birthYears[$year]++;
            }
        }

        ksort($birthYears); // Sort by year ascending
        return $birthYears;
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
