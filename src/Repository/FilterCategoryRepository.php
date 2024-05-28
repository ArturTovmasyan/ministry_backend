<?php

namespace App\Repository;

use App\Entity\FilterCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class FilterCategoryRepository
 * @package App\Repository
 */
class FilterCategoryRepository extends ServiceEntityRepository
{
    /**
     * FilterCategoryRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilterCategory::class);
    }
}
