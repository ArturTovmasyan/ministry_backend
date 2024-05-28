<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Test
 *
 * @ORM\Table(name="test")
 * @ORM\Entity(repositoryClass="App\Repository\TestRepository")
 * @UniqueEntity(fields={"name"}, message="Test with this name already exist.")
 */
class Test
{
    use TimeAwareTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"test", "test_bank", "test_by_assign_id"})
     */
    private $id;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=35)
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 35,
     *      maxMessage = "Test name cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"test", "test_bank", "test_by_assign_id"})
     */
    private $name;

    /**
     * @var Question
     *
     * @ORM\ManyToMany(targetEntity="Question", mappedBy="test", cascade={"persist"}, fetch="EXTRA_LAZY")
     * @Serializer\Groups({"test", "test_bank", "test_by_assign_id"})
     * @Serializer\SerializedName("questions")
     */
    private $question;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="User", inversedBy="test", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_instructor", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $instructor;

    /**
     * @var AssignTest
     *
     * @ORM\OneToMany(targetEntity="AssignTest", mappedBy="test", cascade={"persist", "remove"})
     */
    private $assignTest;

    /**
     * @var int $archived
     *
     * @ORM\Column(name="archived", type="smallint")
     * @Assert\Choice(choices={0, 1}, message="Invalid archive value. Please set 0 or 1")
     * @Assert\NotBlank()
     */
    private $archived = 0;

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getId();
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
     * @return Test
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
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->question = new ArrayCollection();
        $this->assignTest = new ArrayCollection();
    }

    /**
     * Add question.
     *
     * @param Question $question
     *
     * @return Test
     */
    public function addQuestion(Question $question): self
    {
        $this->question[] = $question;

        return $this;
    }

    /**
     * Remove question.
     *
     * @param Question $question
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeQuestion(Question $question): bool
    {
        return $this->question->removeElement($question);
    }

    /**
     * Get question.
     *
     * @return Collection
     */
    public function getQuestion(): Collection
    {
        return $this->question;
    }

    /**
     * @return User|null
     */
    public function getInstructor(): ?User
    {
        return $this->instructor;
    }

    /**
     * @param User|null $instructor
     * @return Test
     */
    public function setInstructor(?User $instructor): self
    {
        $this->instructor = $instructor;

        return $this;
    }

    /**
     * @return Collection|AssignTest[]
     */
    public function getAssignTest(): Collection
    {
        return $this->assignTest;
    }

    /**
     * @param AssignTest $assignTest
     * @return Test
     */
    public function addAssignTest(AssignTest $assignTest): self
    {
        if (!$this->assignTest->contains($assignTest)) {
            $this->assignTest[] = $assignTest;
            $assignTest->setTest($this);
        }

        return $this;
    }

    /**
     * @param AssignTest $assignTest
     * @return Test
     */
    public function removeAssignTest(AssignTest $assignTest): self
    {
        if ($this->assignTest->contains($assignTest)) {
            $this->assignTest->removeElement($assignTest);
            // set the owning side to null (unless already changed)
            if ($assignTest->getTest() === $this) {
                $assignTest->setTest(null);
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getQuestionIds(): array
    {
        $questions = $this->getQuestion();
        $questionIds = [];

        foreach ($questions as $question) {
            $questionIds[] = $question->getId();
        }

        return $questionIds;
    }

    /**
     * @Serializer\Groups({"test", "pass-test", "test_bank"})
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("question_count")
     *
     * @return int
     */
    public function getQuestionsCount(): int
    {
        return \count($this->getQuestion());
    }

    public function getArchived(): ?int
    {
        return $this->archived;
    }

    public function setArchived(int $archived): self
    {
        $this->archived = $archived;

        return $this;
    }
}
