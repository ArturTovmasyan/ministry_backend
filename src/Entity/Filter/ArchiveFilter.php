<?php

namespace App\Entity\Filter;

use App\Entity\Test;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Class ArchiveFilter
 * @package App\Entity\Filter
 */
class ArchiveFilter extends SQLFilter
{
    /**
     * @param ClassMetaData $targetEntity
     * @param string $alias
     * @return string
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $alias):?string
    {
        // check if test entity
        if ($targetEntity->name === Test::class) {
            return $alias . '.archived = 0';
        }

        return '';
    }
}