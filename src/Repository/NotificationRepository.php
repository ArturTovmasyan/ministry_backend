<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    /**
     * NotificationRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * This function is used to get all new notifications by user id
     *
     * @param $userId int
     * @return array
     */
    public function findByUserId($userId): array
    {
        $queryBuilder = $this->createQueryBuilder('n');
        $queryBuilder
            ->select('n.id, n.link, DATE(n.createdAt) as createdAt')
            ->join('n.user', 'u')
            ->where('u.id = :userId AND n.isRead = 0')
            ->orderBy('n.id', 'DESC')
            ->setParameter('userId', $userId);

        return $queryBuilder->getQuery()->getResult();
    }
}
