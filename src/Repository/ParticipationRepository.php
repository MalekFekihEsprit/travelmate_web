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

    /**
     * @param array{name:string,sort:string} $filters
     *
     * @return Participation[]
     */
    public function findBackOfficeParticipations(Voyage $voyage, array $filters): array
    {
        $queryBuilder = $this->createQueryBuilder('participation')
            ->addSelect('user')
            ->innerJoin('participation.user', 'user')
            ->andWhere('participation.voyage = :voyage')
            ->setParameter('voyage', $voyage);

        $name = trim((string) ($filters['name'] ?? ''));
        if ($name !== '') {
            $queryBuilder
                ->andWhere('LOWER(user.nom) LIKE :name')
                ->setParameter('name', '%'.mb_strtolower($name).'%');
        }

        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $queryBuilder->orderBy('user.nom', 'DESC')->addOrderBy('user.prenom', 'DESC'),
            'role_asc' => $queryBuilder->orderBy('participation.role_participation', 'ASC')->addOrderBy('user.id', 'ASC'),
            'role_desc' => $queryBuilder->orderBy('participation.role_participation', 'DESC')->addOrderBy('user.id', 'ASC'),
            default => $queryBuilder->orderBy('user.nom', 'ASC')->addOrderBy('user.prenom', 'ASC'),
        };

        return $queryBuilder->getQuery()->getResult();
    }
}