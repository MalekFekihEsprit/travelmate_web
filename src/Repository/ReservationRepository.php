<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function save(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.email = :email')
            ->setParameter('email', $email)
            ->orderBy('r.dateReservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByActivite(int $activiteId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.activite', 'a')
            ->where('a.id = :activiteId')
            ->setParameter('activiteId', $activiteId)
            ->orderBy('r.dateReservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByActiviteAndDate(int $activiteId, \DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.activite', 'a')
            ->where('a.id = :activiteId')
            ->andWhere('DATE(r.dateReservation) = DATE(:date)')
            ->setParameter('activiteId', $activiteId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
