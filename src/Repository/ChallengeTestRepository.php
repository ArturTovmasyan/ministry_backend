<?php

namespace App\Repository;

use App\Entity\ChallengeTest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ChallengeTest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChallengeTest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChallengeTest[]    findAll()
 * @method ChallengeTest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChallengeTestRepository extends ServiceEntityRepository
{
    /**
     * ChallengeTestRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChallengeTest::class);
    }

    /**
     * This function is used to get data for challenge dashboard rank
     *
     * @return array
     */
    public function findChallengeTestData():array
    {
        $queryBuilder = $this->createQueryBuilder('ch');
        $cloneBuilder = clone $queryBuilder;

        $queryBuilder
            ->select("st.id, CONCAT(st.firstName, ' ', st.lastName) AS full_name,
             (SELECT SUM(cht.studentScore) FROM App:ChallengeTest cht 
             JOIN cht.student s 
             WHERE s.id=st.id ) as score")
            ->join('ch.student', 'st')
            ->groupBy('st.id');

        // get each student data by student/competitor fields
        $stResult = $queryBuilder->getQuery()->getArrayResult();

        $cloneBuilder
            ->select("cm.id, CONCAT(cm.firstName, ' ', cm.lastName) AS full_name,
             (SELECT SUM(cht.competitorScore) FROM App:ChallengeTest cht 
             JOIN cht.competitor c 
             WHERE c.id=cm.id) as score")
            ->join('ch.competitor', 'cm')
            ->groupBy('cm.id');

        $cmResult = $cloneBuilder->getQuery()->getArrayResult();

        return array_merge($stResult, $cmResult);
    }

    /**
     * This function is used to get not finished challenges
     *
     * @param null $studentId
     * @return mixed
     */
    public function findNotFinishedChallenges($studentId = null)
    {
        $queryBuilder = $this->createQueryBuilder('ch')
            ->select('ch')
            ->join('ch.student', 'st')
            ->join('ch.competitor', 'cp')
            ->where('ch.type != :type')
            ->setParameter('type', ChallengeTest::FINISHED);

        if ($studentId) {
            $queryBuilder
                ->andWhere('st.id = :studentId OR cp.id = :studentId')
                ->setParameter('studentId', $studentId);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
