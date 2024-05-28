<?php

namespace App\Repository;

use App\Entity\ChallengeTestHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ChallengeTestHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChallengeTestHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChallengeTestHistory[]    findAll()
 * @method ChallengeTestHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChallengeTestHistoryRepository extends ServiceEntityRepository
{
    /**
     * ChallengeTestHistoryRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChallengeTestHistory::class);
    }

    /**
     * This function is used to get data for challenge dashboard rank
     *
     * @param int $limit
     * @return array
     */
    public function findChallengeTestData($limit):array
    {
        if ($limit === null) {
            $limit = 1000;
        }

        $queryBuilder = $this->createQueryBuilder('ch')
            ->select('ch.country, ch.fullName AS full_name, SUM(ch.score) AS score, ch.student AS user_id')
            ->groupBy('ch.student')
            ->orderBy('score', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }
}
