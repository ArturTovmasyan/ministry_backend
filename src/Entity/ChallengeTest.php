<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChallengeTestRepository")
 */
class ChallengeTest
{
    public const CREATED = 0;
    public const STARTED = 1;
    public const FINISHED = 2;

    use TimeAwareTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Test")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull()
     */
    private $test;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_student", referencedColumnName="id", nullable=false)
     * })
     * @Assert\NotNull()
     */
    private $student;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_competitor", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $competitor;

    /**
     * @ORM\OneToMany(targetEntity="AssignTest", mappedBy="challengeTest", cascade={"persist"})
     */
    private $assignTests;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="integer")
     */
    private $studentScore = 0;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="integer")
     */
    private $competitorScore = 0;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $confirmToken ='';

    /**
     * @var \Datetime
     * @ORM\Column(name="last_checked_date", type="datetime")
     */
    private $lastCheckedDate;

    /**
     * @ORM\Column(type="integer", name="type")
     * @Assert\NotBlank()
     */
    private $type = self::CREATED;

    /**
     * ChallengeTest constructor.
     */
    public function __construct()
    {
        $this->assignTests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): self
    {
        $this->student = $student;

        return $this;
    }

    public function getCompetitor(): ?User
    {
        return $this->competitor;
    }

    public function setCompetitor(?User $competitor): self
    {
        $this->competitor = $competitor;

        return $this;
    }

    public function getStudentScore(): ?int
    {
        return $this->studentScore;
    }

    public function setStudentScore(int $studentScore): self
    {
        $this->studentScore = $studentScore;

        return $this;
    }

    public function getCompetitorScore(): ?int
    {
        return $this->competitorScore;
    }

    public function setCompetitorScore(int $competitorScore): self
    {
        $this->competitorScore = $competitorScore;

        return $this;
    }

    public function getConfirmToken(): ?string
    {
        return $this->confirmToken;
    }

    public function setConfirmToken(?string $confirmToken): self
    {
        $this->confirmToken = $confirmToken;

        return $this;
    }

    /**
     * @return Collection|AssignTest[]
     */
    public function getAssignTests(): Collection
    {
        return $this->assignTests;
    }

    public function addAssignTest(AssignTest $assignTest): self
    {
        if (!$this->assignTests->contains($assignTest)) {
            $this->assignTests[] = $assignTest;
            $assignTest->setChallengeTest($this);
        }

        return $this;
    }

    public function removeAssignTest(AssignTest $assignTest): self
    {
        if ($this->assignTests->contains($assignTest)) {
            $this->assignTests->removeElement($assignTest);
            // set the owning side to null (unless already changed)
            if ($assignTest->getChallengeTest() === $this) {
                $assignTest->setChallengeTest(null);
            }
        }

        return $this;
    }

    public function getLastCheckedDate(): ?\Datetime
    {
        return $this->lastCheckedDate;
    }

    public function setLastCheckedDate(\Datetime $lastCheckedDate): self
    {
        $this->lastCheckedDate = $lastCheckedDate;

        return $this;
    }

    /**
     * @return array
     */
    public function getPlayers():array
    {
        $players = [];
        $studentFullName = $this->student->getFullName();
        $competitorFullName = $this->competitor->getFullName();

        if ($this->getStudentScore() === $this->getCompetitorScore()) {
            $players['players'][] = $studentFullName;
            $players['players'][] = $competitorFullName;
        } elseif ($this->getStudentScore() > $this->getCompetitorScore()) {
            $players['winner_name'] = $studentFullName;
            $players['loser_name'] = $competitorFullName;
        } else {
            $players['winner_name'] = $competitorFullName;
            $players['loser_name'] = $studentFullName;
        }

        return $players;
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
}
