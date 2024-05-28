<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use App\Entity\Traits\UploadPhotoTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

/**
 * Question
 *
 * @ORM\Table(name="question")
 * @ORM\Entity(repositoryClass="App\Repository\QuestionRepository")
 * @UniqueEntity(fields={"name"}, message="Question with this name already exist.")
 */
class Question
{
    use TimeAwareTrait;
    use UploadPhotoTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"test", "pass-test", "test_by_assign_id"})
     */
    private $id;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=2500)
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 2500,
     *      maxMessage = "Question name cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"test", "pass-test", "test_by_assign_id"})
     */
    private $name;

    /**
     * @var string $name
     *
     * @ORM\Column(name="explanation", type="string", length=2500)
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 2500,
     *      maxMessage = "Explanation cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"pass-test", "test_by_assign_id"})
     */
    private $explanation;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="TestFilter", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(name="question_filters",
     *   joinColumns={
     *     @ORM\JoinColumn(name="id_question", referencedColumnName="id", onDelete="CASCADE")
     *   },inverseJoinColumns={
     *     @ORM\JoinColumn(name="id_filter", referencedColumnName="id", onDelete="CASCADE")
     *   })
     * @Serializer\Groups({"test_bank", "test"})
     */
    private $filters;

    /**
     * @var Answer
     *
     * @ORM\OneToMany(targetEntity="Answer", mappedBy="question", cascade={"persist", "remove"})
     * @Serializer\Groups({"test", "pass-test", "test_by_assign_id"})
     */
    private $answer;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Test", inversedBy="question", cascade={"persist"})
     * @ORM\JoinTable(name="question_test",
     *   joinColumns={
     *     @ORM\JoinColumn(name="id_question", referencedColumnName="id", onDelete="CASCADE")
     *   },inverseJoinColumns={
     *     @ORM\JoinColumn(name="id_test", referencedColumnName="id", onDelete="CASCADE")
     *   })
     */
    private $test;

    /**
     * @ORM\OneToMany(targetEntity="PassedQuestion", mappedBy="question", cascade={"persist", "remove"})
     */
    private $passedQuestion;

    /**
     * @var int $name
     *
     * @ORM\Column(name="number", type="integer", nullable=true)
     * @Serializer\Groups({"pass-test", "test_by_assign_id"})
     */
    private $number;

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
     * @return Question
     */
    public function setName(string $name): self
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
     * Set explanation.
     *
     * @param string $explanation
     *
     * @return Question
     */
    public function setExplanation(string $explanation): self
    {
        $this->explanation = $explanation;

        return $this;
    }

    /**
     * Get explanation.
     *
     * @return string
     */
    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->answer = new ArrayCollection();
        $this->filters = new ArrayCollection();
        $this->test = new ArrayCollection();
        $this->passedQuestion = new ArrayCollection();
    }

    /**
     * Add answer.
     *
     * @param Answer $answer
     *
     * @return Question
     */
    public function addAnswer(Answer $answer): self
    {
        $this->answer[] = $answer;

        return $this;
    }

    /**
     * Remove answer.
     *
     * @param Answer $answer
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeAnswer(Answer $answer): bool
    {
        return $this->answer->removeElement($answer);
    }

    /**
     * Get answer.
     *
     * @return Collection
     */
    public function getAnswer(): ?Collection
    {
        return $this->answer;
    }

    /**
     * @return Collection|TestFilter[]
     */
    public function getFilters(): ?Collection
    {
        return $this->filters;
    }

    /**
     * @param TestFilter $filter
     * @return Question
     */
    public function addFilter(TestFilter $filter): self
    {
        if (!$this->filters->contains($filter)) {
            $this->filters[] = $filter;
        }

        return $this;
    }

    /**
     * @param TestFilter $filter
     * @return Question
     */
    public function removeFilter(TestFilter $filter): self
    {
        if ($this->filters->contains($filter)) {
            $this->filters->removeElement($filter);
        }

        return $this;
    }

    /**
     * @return Collection|Test[]
     */
    public function getTest(): ?Collection
    {
        return $this->test;
    }

    /**
     * @param Test $test
     * @return Question
     */
    public function addTest(Test $test): self
    {
        if (!$this->test->contains($test)) {
            $this->test[] = $test;
        }

        return $this;
    }

    /**
     * @param Test $test
     * @return Question
     */
    public function removeTest(Test $test): self
    {
        if ($this->test->contains($test)) {
            $this->test->removeElement($test);
        }

        return $this;
    }

    /**
     * @return Collection|PassedQuestion[]
     */
    public function getPassedQuestion(): Collection
    {
        return $this->passedQuestion;
    }

    /**
     * @param PassedQuestion $passedQuestion
     * @return Question
     */
    public function addPassedQuestion(PassedQuestion $passedQuestion): self
    {
        if (!$this->passedQuestion->contains($passedQuestion)) {
            $this->passedQuestion[] = $passedQuestion;
            $passedQuestion->setQuestion($this);
        }

        return $this;
    }

    /**
     * @param PassedQuestion $passedQuestion
     * @return Question
     */
    public function removePassedQuestion(PassedQuestion $passedQuestion): self
    {
        if ($this->passedQuestion->contains($passedQuestion)) {
            $this->passedQuestion->removeElement($passedQuestion);
            // set the owning side to null (unless already changed)
            if ($passedQuestion->getQuestion() === $this) {
                $passedQuestion->setQuestion(null);
            }
        }

        return $this;
    }

    /**
     * Set custom upload dir for question files
     *
     * @return string
     */
    public static function getUploadDir():string
    {
        return 'upload/photo/question/';
    }

    /**
     * @return int|null
     */
   public function getNumber(): ?int
    {
        return $this->number;
    }

    /**
     * @param int $number
     * @return Question
     */
    public function setNumber(int $number): self
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("file_name")
     * @Serializer\Groups({"test_by_assign_id"})
     * @return string
     */
    public function getFileFullPath():string
    {
        if ($this->getFileName()) {
            return getenv('AWS_HOST').$this->getWebPath();
        }

        return '';
    }
}
