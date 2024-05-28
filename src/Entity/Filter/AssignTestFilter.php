<?php

namespace App\Entity\Filter;

use App\Entity\AssignTest;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Class AssignTestFilter
 * @package App\Entity\Filter
 */
class AssignTestFilter extends SQLFilter
{
    /**
     * @param ClassMetaData $targetEntity
     * @param string $alias
     * @return string
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $alias):?string
    {
        // check if test entity
        if ($targetEntity->name === AssignTest::class) {
            return $alias . '.type = 0';
        }

        return '';
    }
}