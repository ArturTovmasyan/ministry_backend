<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChallengeTestHistoryRepository")
 * @ORM\Table(name="challenge_test_history", indexes={@ORM\Index(name="search_challenge_test_idx", columns={"full_name", "score"})})
 */
class ChallengeTestHistory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", name="student")
     */
    private $student;

    /**
     * @ORM\Column(type="integer", name="score")
     */
    private $score;

    /**
     * @ORM\Column(type="string", length=50, name="full_name")
     */
    private $fullName;

    /**
     * @ORM\Column(type="string", length=25, name="country", nullable=true)
     */
    private $country;

    /**
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", name="updated_at")
     */
    private $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?int
    {
        return $this->student;
    }

    public function setStudent(int $student): self
    {
        $this->student = $student;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }
}
