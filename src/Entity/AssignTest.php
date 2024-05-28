<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity(repositoryClass="App\Repository\AssignTestRepository")
 */
class AssignTest
{
    use TimeAwareTrait;

    public const ASSIGN = 0;
    public const STARTED = 1;
    public const CHALLENGED = 1;
    public const COMPLETED = 2;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     * @Assert\NotBlank()
     */
    private $deadline;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     * @Assert\Range(
     *      min = 60,
     *      max = 1000,
     *      minMessage = "Value cannot be less than {{ limit }}",
     *      maxMessage = "Value cannot be longer than {{ limit }}",
     * )
     */
    private $timeLimit;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     */
    private $expectation;

    /**
     * @ORM\Column(type="smallint")
     * @Assert\NotBlank()
     * @Assert\Choice(choices={0, 1, 2}, message="Invalid status value. Please set 0, 1 or 2")
     */
    private $status = self::ASSIGN;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     */
    private $score = 0;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     */
    private $type = 0;

    /**
     * @var Test
     *
     * @Assert\NotNull(message="Test can not be blank")
     * @ORM\ManyToOne(targetEntity="Test", inversedBy="assignTest")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_test", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $test;

    /**
     * @ORM\ManyToOne(targetEntity="ChallengeTest", inversedBy="assignTests", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_challenge_test", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $challengeTest;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="assignTest", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_student", referencedColumnName="id", nullable=false)
     * })
     */
    private $student;

    /**
     * @ORM\OneToMany(targetEntity="PassedQuestion", mappedBy="assignTest", cascade={"persist", "remove"})
     */
    private $passedQuestions;

    /** @var $studentArray */
    public $studentArray;

    public function __construct()
    {
        $this->passedQuestions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(\DateTimeInterface $deadline): self
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getTimeLimit(): ?int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(int $timeLimit): self
    {
        $this->timeLimit = $timeLimit;

        return $this;
    }

    public function getExpectation(): ?int
    {
        return $this->expectation;
    }

    public function setExpectation(int $expectation): self
    {
        $this->expectation = $expectation;

        return $this;
    }

    public function getTest(): ?Test
    {
        return $this->test;
    }

    public function setTest(?Test $test): self
    {
        $this->test = $test;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): self
    {
        $this->student = $student;

        return $this;
    }

    /**
     * @return Collection|PassedQuestion[]
     */
    public function getPassedQuestions(): Collection
    {
        return $this->passedQuestions;
    }

    /**
     * @param PassedQuestion $passedQuestion
     * @return AssignTest
     */
    public function addPassedQuestion(PassedQuestion $passedQuestion): self
    {
        if (!$this->passedQuestions->contains($passedQuestion)) {
            $this->passedQuestions[] = $passedQuestion;
            $passedQuestion->setAssignTest($this);
        }

        return $this;
    }

    /**
     * @param PassedQuestion $passedQuestion
     * @return AssignTest
     */
    public function removePassedQuestion(PassedQuestion $passedQuestion): self
    {
        if ($this->passedQuestions->contains($passedQuestion)) {
            $this->passedQuestions->removeElement($passedQuestion);
            // set the owning side to null (unless already changed)
            if ($passedQuestion->getAssignTest() === $this) {
                $passedQuestion->setAssignTest(null);
            }
        }

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getChallengeTest(): ?ChallengeTest
    {
        return $this->challengeTest;
    }

    public function setChallengeTest(?ChallengeTest $challengeTest): self
    {
        $this->challengeTest = $challengeTest;

        return $this;
    }

    /**
     * @param int $competitorId
     * @return bool
     */
    public function isCompetitorTest(int $competitorId):bool
    {
        /** @var User $student */
        $student = $this->student;

        return $student && $student->getId() === $competitorId;
    }

    /**
     * @param int $studentId
     * @return bool
     */
    public function isStudentTest(int $studentId):bool
    {
        /** @var User $student */
        $student = $this->student;

        return $student && $student->getId() === $studentId;
    }
}
