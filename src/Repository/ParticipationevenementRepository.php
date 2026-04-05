<?php

namespace App\Repository;

use App\Entity\ParticipationEvenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipationEvenement>
 */
class ParticipationEvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipationEvenement::class);
    }

    /**
     * Trouve toutes les participations d'un utilisateur
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les participations d'un événement
     */
    public function findByEvenement(int $evenementId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.evenement = :evenementId')
            ->setParameter('evenementId', $evenementId)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
