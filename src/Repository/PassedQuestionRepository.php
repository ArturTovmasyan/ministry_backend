<?php

namespace App\Repository;

use App\Entity\AssignTest;
use App\Entity\PassedQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PassedQuestion|null find($id, $lockMode = null, $lockVersion = null)
 * @method PassedQuestion|null findOneBy(array $criteria, array $orderBy = null)
 * @method PassedQuestion[]    findAll()
 * @method PassedQuestion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PassedQuestionRepository extends ServiceEntityRepository
{
    /**
     * PassedQuestionRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PassedQuestion::class);
    }

    /**
     * This function is used to get student analytics data
     *
     * @param int $id
     * @param mixed $schoolId
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findStudentAnalyticsData($id, $schoolId = null)
    {
        $queryBuilder = $this->createQueryBuilder('pq')
            ->select('COUNT(pq.id)')
            ->join('pq.student', 's')
            ->where('s.id = :studentId');

        if ($schoolId) {
            $queryBuilder
                ->join('s.school', 'sc')
                ->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        // get all question count
        $allQuestionCount = $queryBuilder->setParameter('studentId', $id)->getQuery()->getSingleScalarResult();

        $queryBuilder = $this->createQueryBuilder('pq')
            ->select('c.name AS groups, f.name, COUNT(q.id) AS questions_count, 
                            (ROUND(SUM(pq.score)/' . $allQuestionCount . '*100)) AS score')
            ->join('pq.student', 'st')
            ->join('pq.question', 'q')
            ->join('q.filters', 'f')
            ->join('f.category', 'c')
            ->where('st.id = :studentId');

        if ($schoolId) {
            $queryBuilder
                ->join('st.school', 'sc')
                ->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        $queryBuilder
            ->groupBy('f.id')
            ->setParameter('studentId', $id);

       return  $queryBuilder->getQuery()->getResult();
    }

    /**
     * This function is used to get passed questions ids
     *
     * @param $assignTestId
     * @return mixed
     */
    public function findCompletedQuestionIds($assignTestId)
    {
        $result = $this->createQueryBuilder('pq')
            ->select('q.id')
            ->join('pq.assignTest', 'at')
            ->join('pq.question', 'q')
            ->where('at.id = :assignTestId')
            ->setParameter('assignTestId', $assignTestId)
            ->groupBy('q.id')
            ->getQuery()
            ->getResult();

        // fetch id from array result
        $result = array_map(static function ($item) {
            return $item['id'];
        }, $result);

        return $result;
    }

    /**
     * This function is used to get last passed question data
     *
     * @param $assignTestId
     * @param $studentId
     *
     * @return mixed
     */
    public function findLastPassedQuestionData($assignTestId, $studentId)
    {
        $result = $this->createQueryBuilder('pq')
            ->select('q.id AS question_id, pq.answer AS answer_id, at.id AS assign_test_id, pq.marked')
            ->join('pq.assignTest', 'at')
            ->join('pq.question', 'q')
            ->join('pq.student', 'st')
            ->where('at.id = :assignTestId AND st.id = :studentId AND at.status = :status')
            ->orderBy('pq.id', 'DESC')
            ->setParameters(['assignTestId' => $assignTestId, 'studentId' => $studentId, 'status' => AssignTest::STARTED])
            ->setFirstResult(0)
            ->setMaxResults(1);

        return $result->getQuery()->getResult();
    }

    /**
     * This function is used to get correct answers count from test
     *
     * @param $assignTestId
     * @param $studentId
     *
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findCorrectAnswersCount($assignTestId, $studentId):int
    {
        $result = $this->createQueryBuilder('pq')
            ->select('COUNT(pq.id)')
            ->join('pq.assignTest', 'at')
            ->join('pq.student', 'st')
            ->where('at.id = :assignTestId AND st.id = :studentId AND pq.score = 1 AND at.status = :status')
            ->orderBy('pq.id', 'DESC')
            ->setParameters(['assignTestId' => $assignTestId, 'studentId' => $studentId, 'status' => PassedQuestion::ALL_FINISHED]);

        return (int)$result->getQuery()->getSingleScalarResult();
    }
}
