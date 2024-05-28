<?php

namespace App\Repository;

use App\Entity\TestFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class TestFilterRepository
 * @package App\Repository\Src\Entity
 */
class TestFilterRepository extends ServiceEntityRepository
{
    /**
     * QuestionRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestFilter::class);
    }
}
