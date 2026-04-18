<?php

namespace App\Repository;

use App\Entity\Destination;
use App\Entity\FavoriteDestination;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteDestination>
 */
class FavoriteDestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteDestination::class);
    }

    public function findOneByDestinationAndUser(Destination $destination, User $user): ?FavoriteDestination
    {
        return $this->findOneBy([
            'destination' => $destination,
            'user' => $user,
        ]);
    }
}