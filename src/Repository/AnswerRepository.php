<?php

namespace App\Repository;

use App\Entity\Answer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class AnswerRepository
 * @package App\Repository
 */
class AnswerRepository extends ServiceEntityRepository
{
    /**
     * AnswerRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Answer::class);
    }

    /**
     * This function is used to get right answer value
     *
     * @param $questionId int
     * @param $answerId int
     * @return int
     * @throws NonUniqueResultException
     */
    public function findRightAnswerById($questionId, $answerId): int
    {
        $queryBuilder = $this->createQueryBuilder('an');
        $queryBuilder
            ->select('an.isRight')
            ->join('an.question', 'q')
            ->where('q.id = :questionId AND an.id = :answerId')
            ->setParameter('questionId', $questionId)
            ->setParameter('answerId', $answerId);

        $result = $queryBuilder->getQuery()->getOneOrNullResult();
        return $result['isRight'] ?? 0;
    }
}
