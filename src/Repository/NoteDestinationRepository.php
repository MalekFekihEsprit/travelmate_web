<?php

namespace App\Repository;

use App\Entity\Destination;
use App\Entity\NoteDestination;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NoteDestination>
 */
class NoteDestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NoteDestination::class);
    }

    public function findOneByDestinationAndUser(Destination $destination, User $user): ?NoteDestination
    {
        return $this->findOneBy([
            'destination' => $destination,
            'user' => $user,
        ]);
    }

    public function getAverageForDestination(Destination $destination): float
    {
        $avg = $this->createQueryBuilder('n')
            ->select('AVG(n.note)')
            ->andWhere('n.destination = :destination')
            ->setParameter('destination', $destination)
            ->getQuery()
            ->getSingleScalarResult();

        return $avg !== null ? round((float) $avg, 2) : 0.0;
    }
}
