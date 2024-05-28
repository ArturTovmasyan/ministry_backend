<?php

namespace App\Repository;

use App\Entity\AssignTest;
use App\Entity\Test;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    /**
     * UserRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * This function is used to get user id by email
     *
     * @param $email
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getUserByEmail($email)
    {
        $queryBuilder = $this->createQueryBuilder('u');
        $queryBuilder
            ->select('u.id')
            ->where('u.email = :email')
            ->setParameter('email', $email);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * This function is used to get students by class ids and not assigned this test
     *
     * @param $classIds
     * @param $testId
     * @return mixed
     */
    public function getStudentsByClass($classIds, $testId)
    {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->select('s, at, t')
            ->join('s.class', 'c')
            ->leftJoin('s.assignTest', 'at')
            ->leftJoin('at.test', 't')
            ->where('c.id IN (:classIds) AND s.type = :studentType AND s.classToken IS NULL')
            ->setParameter('classIds', $classIds)
            ->setParameter('studentType', User::STUDENT);

        /** @var ArrayCollection $studentArray */
        $studentArray = $queryBuilder->getQuery()->getResult();

        // exclude students where already assigned to current test
        foreach ($studentArray as $key => $student) {
            /** @var ArrayCollection $assignTests */
            $assignTests = $student->getAssignTest();

            if (\count($assignTests) > 0) {
                /** @var AssignTest $assignTest */
                foreach ($assignTests as $assignTest) {
                    /** @var Test $test */
                    $test = $assignTest->getTest();

                    if ($test->getId() === $testId) {
                        unset($studentArray[$key]);
                    }
                }
            }
        }

        return $studentArray;
    }

    /**
     * This function is used to get spare students for related to class
     *
     * @param int|null $schoolId
     * @return mixed
     */
    public function getSpareStudents($schoolId)
    {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->select('s')
            ->leftJoin('s.class', 'c')
            ->where('c.id IS NULL AND s.type = :studentType')
            ->setParameter('studentType', User::STUDENT);

        if ($schoolId) {
            $queryBuilder
                ->join('s.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * This function is used to get students without logged (current) student
     *
     * @param int $currentUserId
     * @return mixed
     */
    public function findStudentsWithoutLogged($currentUserId)
    {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->select("s.id, s.email, CONCAT(s.firstName, ' ', s.lastName) AS full_name")
            ->where('s.id != :userId')
            ->orderBy('s.firstName')
            ->setParameter('userId', $currentUserId);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * This function is used to get student by id and class assign confirm token
     *
     * @param $studentId
     * @param $token
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getStudentByIdAndToken($studentId, $token)
    {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->select('s')
            ->where('s.id = :studentId AND s.classToken = :token')
            ->setParameter('studentId', $studentId)
            ->setParameter('token', $token);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * This function is used to get instructor full name
     *
     * @param $id
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getInstructorFullName($id)
    {
        $queryBuilder = $this->createQueryBuilder('i');
        $queryBuilder
            ->select('i.lastName', 'i.firstName')
            ->where('i.id = :id')
            ->setParameter('id', $id);

        $result = $queryBuilder->getQuery()->getOneOrNullResult();

        return $result['firstName'] . ' ' . $result['lastName'];
    }

    /**
     * This function is used to get instructor classes data for view
     *
     * @param $instructorId
     * @param $schoolId
     * @return mixed
     */
    public function findInstructorClassesData($instructorId, $schoolId)
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->select("st.id AS student_id, ic.name, ic.id AS class_id,
              (CASE WHEN st.classToken IS NULL THEN CONCAT(st.firstName, ' ', st.lastName) ELSE '*pending' END) AS full_name,
              st.email, at.score AS last_score,
              (ROUND(SUM(at.score)/COUNT(at.id))) AS gpa,
              MAX(at.score) AS max, MIN(at.score) AS min")
            ->join('i.studentClass', 'ic');

        if ($schoolId) {
            $queryBuilder
                ->join('i.school', 'school')
                ->join('school.user', 'st', Expr\Join::WITH, 'st.type = :studentType')
                ->where('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId)
                ->setParameter('studentType', User::STUDENT);

        } else {
            $queryBuilder->leftJoin('ic.student', 'st');
        }

        $queryBuilder
            ->leftJoin('st.assignTest', 'at', Expr\Join::WITH, 'at.status = :status')
            ->andWhere('i.id = :instructorId')
            ->setParameter('instructorId', $instructorId)
            ->setParameter('status', AssignTest::COMPLETED)
            ->groupBy('st.id')
            ->addGroupBy('ic.id')
            ->orderBy('gpa', 'DESC');

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * This function is used to get student history data
     *
     * @param int $id
     * @param int|null $schoolId
     * @return mixed
     */
    public function findStudentHistoryData($id, $schoolId)
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->select("CONCAT(s.firstName, ' ', s.lastName) AS full_name, t.name AS test_name, 
                             DATE_FORMAT(at.updatedAt, '%Y-%m-%d') AS complete_date, 
                             COUNT(DISTINCT(q.id)) AS question_count,
                             at.score AS score")
            ->join('s.assignTest', 'at')
            ->join('at.test', 't')
            ->join('t.question', 'q')
            ->where('s.id = :studentId AND at.status = :status');

        if ($schoolId) {
            $queryBuilder
                ->join('s.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        $queryBuilder
            ->groupBy('t.id')
            ->orderBy('score', 'DESC')
            ->setParameter('studentId', $id)
            ->setParameter('status', AssignTest::COMPLETED);

        return $queryBuilder->getQuery()->getResult();
    }
}
