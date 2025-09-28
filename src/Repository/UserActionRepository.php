<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAction>
 */
class UserActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAction::class);
    }

    public function save(UserAction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserAction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return UserAction[]
     */
    public function findRecentActionsForUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return UserAction[]
     */
    public function findActionsByType(User $user, string $actionType, int $limit = 20): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.actionType = :actionType')
            ->setParameter('user', $user)
            ->setParameter('actionType', $actionType)
            ->orderBy('ua.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActionsForUser(User $user): int
    {
        return $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getActionTypeStatsForUser(User $user): array
    {
        $result = $this->createQueryBuilder('ua')
            ->select('ua.actionType, COUNT(ua.id) as count')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->groupBy('ua.actionType')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['actionType']] = (int) $row['count'];
        }

        return $stats;
    }

    public function getGlobalActionTypeStats(): array
    {
        $result = $this->createQueryBuilder('ua')
            ->select('ua.actionType, COUNT(ua.id) as count')
            ->groupBy('ua.actionType')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['actionType']] = (int) $row['count'];
        }

        return $stats;
    }

    public function getActionsByDay(int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        $since = $since->setTime(0, 0, 0); // Commencer au début de la journée

        $actions = $this->createQueryBuilder('ua')
            ->select('ua.createdAt')
            ->where('ua.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('ua.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [];

        // Compter les actions par jour (ne garder que les jours avec des actions)
        foreach ($actions as $action) {
            $date = $action['createdAt']->format('Y-m-d');
            if (isset($stats[$date])) {
                $stats[$date]++;
            } else {
                $stats[$date] = 1;
            }
        }

        // S'assurer que les clés sont triées chronologiquement
        ksort($stats);

        return $stats;
    }

    public function getMostActiveUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('ua')
            ->select('u.fullname, u.email, COUNT(ua.id) as actionCount')
            ->join('ua.user', 'u')
            ->groupBy('ua.user')
            ->orderBy('actionCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTotalActions(): int
    {
        return $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getRecentGlobalActions(int $limit = 20): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.user', 'u')
            ->orderBy('ua.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
