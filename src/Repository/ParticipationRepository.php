<?php

namespace App\Repository;

use App\Entity\Participation;
use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * @return Participation[]
     */
    public function findByVoyageOrdered(Voyage $voyage): array
    {
        return $this->createQueryBuilder('participation')
            ->addSelect('user')
            ->innerJoin('participation.user', 'user')
            ->andWhere('participation.voyage = :voyage')
            ->setParameter('voyage', $voyage)
            ->orderBy('user.nom', 'ASC')
            ->addOrderBy('user.prenom', 'ASC')
            ->addOrderBy('user.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}