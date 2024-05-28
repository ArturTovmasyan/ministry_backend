<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PassedQuestionRepository")
 */
class PassedQuestion
{
    public const ALL = 0;
    public const MARKED = 1;
    public const ALL_FINISHED = 2;
    public const LIMIT = 10;

    use TimeAwareTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="integer")
     */
    private $answer = 0;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="smallint")
     * @Assert\Choice(choices={0, 1}, message="Invalid score value. Please set 0 or 1")
     */
    private $score = 0;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="boolean")
     */
    private $marked = 0;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="passedQuestion")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_student", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $student;

    /**
     * @ORM\ManyToOne(targetEntity="Question", inversedBy="passedQuestion")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_question", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $question;

    /**
     * @ORM\ManyToOne(targetEntity="AssignTest", inversedBy="passedQuestions", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_assign_test", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $assignTest;
    
    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnswer(): ?int
    {
        return $this->answer;
    }

    public function setAnswer(int $answer): self
    {
        $this->answer = $answer;

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

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): self
    {
        $this->question = $question;

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

    public function getAssignTest(): ?AssignTest
    {
        return $this->assignTest;
    }

    public function setAssignTest(?AssignTest $assignTest): self
    {
        $this->assignTest = $assignTest;

        return $this;
    }

    public function getMarked(): ?bool
    {
        return $this->marked;
    }

    public function setMarked(bool $marked): self
    {
        $this->marked = $marked;

        return $this;
    }
}
