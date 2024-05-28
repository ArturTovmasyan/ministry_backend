<?php

namespace App\Repository;

use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method School|null find($id, $lockMode = null, $lockVersion = null)
 * @method School|null findOneBy(array $criteria, array $orderBy = null)
 * @method School[]    findAll()
 * @method School[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SchoolRepository extends ServiceEntityRepository
{
    /**
     * SchoolRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, School::class);
    }

    /**
     * @return array|null
     */
    public function findSchools():?array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id, s.name, s.logo')
            ->getQuery()
            ->getResult()
        ;
    }
}
