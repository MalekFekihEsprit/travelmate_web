<?php

namespace App\Repository;

use App\Entity\DestinationVoyageNotification;
use App\Entity\User;
use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DestinationVoyageNotification>
 */
class DestinationVoyageNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DestinationVoyageNotification::class);
    }

    /**
     * @return DestinationVoyageNotification[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.voyage', 'v')
            ->addSelect('v')
            ->leftJoin('v.destination', 'd')
            ->addSelect('d')
            ->where('n.user = :user')
            ->andWhere('n.is_dismissed = :isDismissed')
            ->setParameter('user', $user)
            ->setParameter('isDismissed', false)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndVoyage(User $user, Voyage $voyage): ?DestinationVoyageNotification
    {
        return $this->findOneBy([
            'user' => $user,
            'voyage' => $voyage,
        ]);
    }

    public function dismissByUserAndVoyageId(User $user, int $voyageId): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.is_dismissed', ':dismissed')
            ->where('n.user = :user')
            ->andWhere('IDENTITY(n.voyage) = :voyageId')
            ->setParameter('dismissed', true)
            ->setParameter('user', $user)
            ->setParameter('voyageId', $voyageId)
            ->getQuery()
            ->execute();
    }

    public function dismissAllByUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.is_dismissed', ':dismissed')
            ->where('n.user = :user')
            ->andWhere('n.is_dismissed = :alreadyDismissed')
            ->setParameter('dismissed', true)
            ->setParameter('alreadyDismissed', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
