<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccessGroup>
 */
class AccessGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessGroup::class);
    }

    public function getHighestRole(): AccessGroup
    {
        $qb = $this->createQueryBuilder('a');
        $accessGroup = $qb->orderBy('a.position', 'ASC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if (null === $accessGroup) {
            return new AccessGroup();
        }

        return $accessGroup;
    }

    public function getSuperiorRole(AccessGroup $accessGroup): ?AccessGroup
    {
        $qb = $this->createQueryBuilder('a');
        $accessGroup = $qb->andWhere('a.position < :position')->setParameter('position', $accessGroup->getPosition())->orderBy('a.position', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return null === $accessGroup ? $this->createQueryBuilder('a')
            ->andWhere('a.position != :position')
            ->setParameter('position', $accessGroup->getPosition())
            ->orderBy('a.position', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() : $accessGroup;
    }

    public function getInferiorRole(AccessGroup $accessGroup): ?AccessGroup
    {
        $qb = $this->createQueryBuilder('a');
        $accessGroup = $qb->andWhere('a.position > :position')->setParameter('position', $accessGroup->getPosition())->orderBy('a.position', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return null === $accessGroup ? $this->createQueryBuilder('a')
            ->andWhere('a.position != :position')
            ->setParameter('position', $accessGroup->getPosition())
            ->orderBy('a.position', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() : $accessGroup;
    }

    public function getLowestRole(): AccessGroup
    {
        $qb = $this->createQueryBuilder('a');
        $accessGroup = $qb->orderBy('a.position', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if (null === $accessGroup) {
            return new AccessGroup();
        }

        return $accessGroup;
    }

    public function getHigherRoles(?AccessGroup $getOwnerRole): array
    {
        $qb = $this->createQueryBuilder('a');

        return $qb->andWhere('a.position < :position')->setParameter('position', $getOwnerRole?->getPosition() ?? 1)->getQuery()->getResult();
    }
}
