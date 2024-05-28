<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="student_class", uniqueConstraints={@ORM\UniqueConstraint(name="IDX_duplicate_student_class", columns={"name", "id_instructor"})})
 * @ORM\Entity(repositoryClass="App\Repository\StudentClassRepository")
 * @UniqueEntity(fields={"name", "instructor"}, message="Class with this name already exist for current instructor")
 */
class StudentClass
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=30)
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 30,
     *      maxMessage = "Student class name cannot be longer than {{ limit }} characters"
     * )
     */
    private $name;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="User", inversedBy="studentClass", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_instructor", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $instructor;

    /**
     * @var User
     *
     * @Assert\Count(
     *      max = 100,
     *      maxMessage = "You cannot specify more than {{ limit }} student in class"
     * )
     *
     * @ORM\OneToMany(targetEntity="User", mappedBy="class", cascade={"persist"})
     */
    private $student;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->getName();
    }


    /**
     * StudentClass constructor.
     */
    public function __construct()
    {
        $this->student = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return StudentClass
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
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
     * @return StudentClass
     */
    public function setInstructor(?User $instructor): self
    {
        $this->instructor = $instructor;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getStudent(): Collection
    {
        return $this->student;
    }

    /**
     * @param User $student
     * @return StudentClass
     */
    public function addStudent(User $student): self
    {
        if (!$this->student->contains($student)) {
            $this->student[] = $student;
            $student->setClass($this);
        }

        return $this;
    }

    /**
     * @param User $student
     * @return StudentClass
     */
    public function removeStudent(User $student): self
    {
        if ($this->student->contains($student)) {
            $this->student->removeElement($student);
            // set the owning side to null (unless already changed)
            if ($student->getClass() === $this) {
                $student->setClass(null);
            }
        }

        return $this;
    }

    /**
     * This function is used to return students email
     *
     * @return array
     */
    public function getStudentsEmail(): ?array
    {
        $students = $this->student;
        $emails = [];

        /** @var User $student */
        foreach ($students as $student) {
            $emails[] = $student->getEmail();
        }

        return $emails;
    }
}
