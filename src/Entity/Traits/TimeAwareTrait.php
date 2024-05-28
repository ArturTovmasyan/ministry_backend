<?php

namespace App\Entity\Traits;

use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Trait TimeAwareTrait
 * @package App\Components\Entity
 */
trait TimeAwareTrait
{
    /**
     * @var \Datetime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var \Datetime
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /** ======================================================== **
     * Getters, setters.
     ** ======================================================== **/

    /**
     * @return \Datetime
     */
    public function getCreatedAt(): \Datetime
    {
        return $this->createdAt;
    }

    /**
     * @param \Datetime $createdAt
     */
    public function setCreatedAt(\Datetime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \Datetime
     */
    public function getUpdatedAt(): \Datetime
    {
        return $this->updatedAt;
    }

    /**
     * @param \Datetime $updatedAt
     */
    public function setUpdatedAt(\Datetime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}