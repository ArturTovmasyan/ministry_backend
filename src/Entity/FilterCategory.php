<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use JMS\Serializer\Annotation as Serializer;

/**
 * FilterCategory
 *
 * @ORM\Table(name="filter_category")
 * @ORM\Entity(repositoryClass="App\Repository\FilterCategoryRepository")
 * @UniqueEntity(fields={"name"}, message="Sorry, filter category with this name already exist.")
 */
class FilterCategory
{
    use TimeAwareTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\SerializedName("category_id")
     * @Serializer\Groups({"filter", "test_bank", "test"})
     */
    private $id;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @ORM\Column(name="name", type="string", length=50, unique=true)
     * @Serializer\SerializedName("category_name")
     * @Serializer\Groups({"filter", "test_bank", "test"})
     */
    private $name;

    /**
     * @var Question
     *
     * @ORM\OneToMany(targetEntity="TestFilter", mappedBy="category", cascade={"persist", "remove"})
     * @Serializer\Groups({"filter"})
     */
    private $filters;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return FilterCategory
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->filters = new ArrayCollection();
    }

    /**
     * Add filter.
     *
     * @param TestFilter $filter
     *
     * @return FilterCategory
     */
    public function addFilter(TestFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Remove filter.
     *
     * @param TestFilter $filter
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeFilter(TestFilter $filter): bool
    {
        return $this->filters->removeElement($filter);
    }

    /**
     * @return Collection|TestFilter[]
     */
    public function getFilters(): Collection
    {
        return $this->filters;
    }
}
