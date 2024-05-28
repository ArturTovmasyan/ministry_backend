<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

/**
 * TestFilter
 *
 * @ORM\Table(name="test_filter", uniqueConstraints={@ORM\UniqueConstraint(name="IDX_duplicate_filter", columns={"name", "id_filter_category"})})
 * @ORM\Entity(repositoryClass="App\Repository\TestFilterRepository")
 * @UniqueEntity(fields={"name", "filter"}, message="Sorry, filter with this name and category already exist.")
 */
class TestFilter
{
    use TimeAwareTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"filter", "test_bank", "test"})
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=50, unique=true)
     * @Assert\Length(
     *      max = 50,
     *      maxMessage = "Filter name cannot be longer than {{ limit }} characters"
     * )
     * @Assert\NotBlank()
     * @Serializer\Groups({"filter", "test_bank", "test"})
     */
    private $name;

    /**
     * @var FilterCategory
     *
     * @ORM\ManyToOne(targetEntity="FilterCategory", inversedBy="filters", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_filter_category", referencedColumnName="id", nullable=false)
     * })
     *
     * @Serializer\Groups({"test_bank", "test"})
     */
    private $category;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

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
     * @return TestFilter
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
     * @return FilterCategory|null
     */
    public function getCategory(): ?FilterCategory
    {
        return $this->category;
    }

    /**
     * @param FilterCategory|null $category
     * @return TestFilter
     */
    public function setCategory(?FilterCategory $category): self
    {
        $this->category = $category;

        return $this;
    }
}
