<?php

namespace App\Repository;

use App\Entity\StudentClass;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StudentClass|null find($id, $lockMode = null, $lockVersion = null)
 * @method StudentClass|null findOneBy(array $criteria, array $orderBy = null)
 * @method StudentClass[]    findAll()
 * @method StudentClass[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StudentClassRepository extends ServiceEntityRepository
{
    /**
     * StudentClassRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentClass::class);
    }

    /**
     * This function is used to get students data in class
     *
     * @param int $studentId
     * @param mixed $schoolId
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findStudentsInClassData($studentId, $schoolId = null)
    {
        $studentsInClass = [];

        $queryBuilder = $this->createQueryBuilder('sc')
            ->select('sc.id')
            ->join('sc.student', 's')
            ->where('s.id = :studentId')
            ->setParameter('studentId', $studentId);

        if ($schoolId) {
            $queryBuilder
                ->join('s.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        $classId = $queryBuilder->getQuery()->getOneOrNullResult();

        if (\is_array($classId) && \array_key_exists('id', $classId)) {

            $classId = (int)$classId['id'];

            $queryBuilder = $this->createQueryBuilder('sc')
                ->select("CONCAT(s.firstName, ' ', s.lastName) AS full_name, 
                                (ROUND(SUM(at.score)/COUNT(at.id))) AS gpa")
                ->join('sc.student', 's')
                ->join('s.assignTest', 'at')
                ->where('sc.id = :classId')
                ->setParameter('classId', $classId);

            if ($schoolId) {
                $queryBuilder
                    ->join('s.school', 'school')
                    ->andWhere('school.id = :schoolId')
                    ->setParameter('schoolId', $schoolId);
            }

            $studentsInClass = $queryBuilder
                ->groupBy('s.id')
                ->orderBy('gpa', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $studentsInClass;
    }

    /**
     * This function is used to get instructor classes list
     *
     * @param $id
     * @param $schoolId
     * @return mixed
     */
    public function findClassesByInstructorId($id, $schoolId = null)
    {
        $queryBuilder = $this->createQueryBuilder('sc')
            ->select('sc.id, sc.name')
            ->join('sc.instructor', 'i')
            ->where('i.id = :instructorId')
            ->setParameter('instructorId', $id);

        if ($schoolId) {
            $queryBuilder
                ->join('i.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

       return $queryBuilder->getQuery()->getResult();
    }
}
