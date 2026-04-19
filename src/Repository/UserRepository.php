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
            $term = '%' . mb_strtolower(trim($term)) . '%';

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

    public function getRegistrationsWindowByDay(int $days = 7, int $offsetDays = 0): array
    {
        $offsetDays = max(0, $offsetDays);

        $endDate = (new \DateTimeImmutable('today'))
            ->modify(sprintf('-%d days', $offsetDays))
            ->setTime(23, 59, 59);

        $startDate = $endDate
            ->modify(sprintf('-%d days', $days - 1))
            ->setTime(0, 0, 0);

        return $this->getRegistrationsWindowByDateRange($startDate, $endDate);
    }

    public function getRegistrationsByDayExtended(int $days = 30, int $offsetDays = 0): array
    {
        return $this->getRegistrationsWindowByDay($days, $offsetDays);
    }

    public function getRegistrationGrowth(): array
    {
        $last30 = $this->getRegistrationsByDayExtended(30, 0);
        $previous30 = $this->getRegistrationsByDayExtended(30, 30);

        return [
            'last30Total' => array_sum($last30),
            'previous30Total' => array_sum($previous30),
            'percentageChange' => $this->calculatePercentageChange(array_sum($previous30), array_sum($last30)),
        ];
    }

    /**
     * Méthode principale utilisée par le contrôleur stats AJAX.
     */
    public function getRegistrationsWindowByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $startDate = $startDate->setTime(0, 0, 0);
        $endDate = $endDate->setTime(23, 59, 59);

        $results = $this->createQueryBuilder('u')
            ->select('u.created_at AS createdAt')
            ->where('u.created_at BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('u.created_at', 'ASC')
            ->getQuery()
            ->getResult();

        $registrations = [];
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            $endDate->modify('+1 day')
        );

        foreach ($period as $date) {
            $registrations[$date->format('Y-m-d')] = 0;
        }

        foreach ($results as $result) {
            $createdAt = $result['createdAt'] ?? null;

            if ($createdAt instanceof \DateTimeInterface) {
                $key = $createdAt->format('Y-m-d');
                if (array_key_exists($key, $registrations)) {
                    $registrations[$key]++;
                }
            }
        }

        return $registrations;
    }

    /**
     * Alias de compatibilité si ailleurs tu appelles encore l’ancien nom.
     */
    public function getRegistrationsByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->getRegistrationsWindowByDateRange($startDate, $endDate);
    }

    private function calculatePercentageChange($old, $new): float
    {
        if ($old == 0) {
            return $new > 0 ? 100 : 0;
        }

        return round((($new - $old) / $old) * 100, 1);
    }

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
            $birthdate = $user['date_naissance'] ?? null;

            if (!$birthdate) {
                $distribution['unknown']++;
                continue;
            }

            $age = $birthdate->diff($now)->y;

            if ($age < 18) {
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
            $birthdate = $user['date_naissance'] ?? null;
            if ($birthdate) {
                $totalAge += $birthdate->diff($now)->y;
                $count++;
            }
        }

        return $count > 0 ? round($totalAge / $count, 1) : 0;
    }

    public function getYoungestAge(): ?int
    {
        $result = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->orderBy('u.date_naissance', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $birthdate = $result['date_naissance'] ?? null;

        return $birthdate ? $birthdate->diff(new \DateTime())->y : null;
    }

    public function getOldestAge(): ?int
    {
        $result = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->orderBy('u.date_naissance', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $birthdate = $result['date_naissance'] ?? null;

        return $birthdate ? $birthdate->diff(new \DateTime())->y : null;
    }

    public function getUsersByBirthYear(): array
    {
        $users = $this->createQueryBuilder('u')
            ->select('u.date_naissance')
            ->where('u.date_naissance IS NOT NULL')
            ->getQuery()
            ->getResult();

        $birthYears = [];

        foreach ($users as $user) {
            $birthdate = $user['date_naissance'] ?? null;
            if ($birthdate) {
                $year = $birthdate->format('Y');
                if (!isset($birthYears[$year])) {
                    $birthYears[$year] = 0;
                }
                $birthYears[$year]++;
            }
        }

        ksort($birthYears);

        return $birthYears;
    }

    public function findVerifiedUsersWithFaceEmbedding(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.face_embedding IS NOT NULL')
            ->andWhere('u.face_embedding <> :empty')
            ->andWhere('u.is_verified = :verified')
            ->setParameter('empty', '')
            ->setParameter('verified', true)
            ->orderBy('u.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }
}