<?php

namespace App\Repository;

use App\Entity\AssignTest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr;

/**
 * @method AssignTest|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssignTest|null findOneBy(array $criteria, array $orderBy = null)
 * @method AssignTest[]    findAll()
 * @method AssignTest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AssignTestRepository extends ServiceEntityRepository
{
    /**
     * AssignTestRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssignTest::class);
    }

    /**
     * This function is used to get assign tests or single test by ids
     *
     * @param $studentId int
     * @param $assignTestId int
     * @return array
     * @throws NonUniqueResultException
     */
    public function findAssignTestData($studentId, $assignTestId = null):?array
    {
        $queryBuilder = $this->createQueryBuilder('at');
        $queryBuilder
            ->select("at.id, DATE_FORMAT(at.deadline, '%Y-%m-%d') AS deadline, at.timeLimit, at.score,  
                            t.id AS test_id, t.name AS test_name, s.id AS student_id, at.type AS is_challenge_test,
                            CONCAT(s.firstName, ' ', s.lastName) AS student_name")
            ->join('at.student', 's')
            ->join('at.test', 't')
            ->where('s.id = :studentId')
            ->setParameter('studentId', $studentId);

        if ($assignTestId) {
            $queryBuilder
                ->andWhere('at.id = :assignTestId')
                ->setParameter('assignTestId', $assignTestId);

            $result = $queryBuilder->getQuery()->getOneOrNullResult();
        } else {
            $result = $queryBuilder->getQuery()->getResult();
        }

        return $result;
    }

    /**
     * This function is used to get student tests data
     *
     * @param int $id
     * @param mixed $schoolId
     * @return array
     * @throws NonUniqueResultException
     */
    public function findStudentTestData($id, $schoolId = null): array
    {
        // get student GPA score and full name
        $queryBuilder = $this->createQueryBuilder('at')
            ->select("cl.name, (ROUND(SUM(at.score)/COUNT(at.id))) AS gpa,
             CONCAT(st.firstName, ' ', st.lastName) AS full_name")
            ->join('at.student', 'st')
            ->join('st.class', 'cl')
            ->where('st.id = :studentId')
            ->setParameter('studentId', $id);

        if ($schoolId) {
            $queryBuilder
                ->join('st.school', 'sc')
                ->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        $gpa = $queryBuilder->getQuery()->getOneOrNullResult();

        // get student tests data
        $queryBuilder = $this->createQueryBuilder('at')
            ->select("t.id AS test_id, at.id AS assign_test_id,
                             t.name AS test_name, DATE_FORMAT(at.deadline, '%Y-%m-%d') AS deadline,
                             at.timeLimit AS time_limit, at.score, at.status, at.type AS is_challenge")
            ->join('at.student', 'st')
            ->join('at.test', 't')
            ->where('st.id = :studentId')
            ->setParameter('studentId', $id);

        if ($schoolId) {
            $queryBuilder
                ->join('st.school', 'sc')
                ->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        $tests = $queryBuilder
            ->groupBy('t.id')
            ->orderBy('deadline', 'DESC')
            ->getQuery()
            ->getResult();

        return ['gpa' => (int)$gpa['gpa'], 'class_name' => $gpa['name'], 'student_name' => $gpa['full_name'], 'tests' => $tests];
    }

    /**
     * This function is used to get test data by class id
     *
     * @param int $classId
     * @return array
     */
    public function getTesByClassId($classId): array
    {
        $queryBuilder = $this->createQueryBuilder('at');
        $queryBuilder
            ->select('at, t')
            ->join('at.test', 't')
            ->join('at.student', 'st')
            ->join('st.class', 'cl', Expr\Join::WITH, 'cl.id = :classId')
            ->groupBy('t.id')
            ->setParameter('classId', $classId);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * This function is used to find by test and student ids
     *
     * @param int $testId
     * @param array $studentIds
     * @return bool
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function findByTestAndStudent(int $testId, array $studentIds): bool
    {
        $queryBuilder = $this->createQueryBuilder('at');
        $queryBuilder
            ->select('COUNT(at.id)')
            ->join('at.test', 't')
            ->join('at.student', 'st')
            ->where('t.id = :testId AND st.id IN (:studentIds)')
            ->setParameter('testId', $testId)
            ->setParameter('studentIds', $studentIds);

        $result = $queryBuilder->getQuery()->getSingleScalarResult();

        return $result ? true : false;
    }
}
