<?php

namespace App\Entity;

use App\Entity\Traits\HumanTrait;
use App\Entity\Traits\SecurityTrait;
use App\Entity\Traits\TimeAwareTrait;
use App\Stripe\Entity\Subscription;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\ORM\Mapping as ORM;
use DateTime;


/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @UniqueEntity(fields={"email"}, message="User with this email already exist")
 * @ORM\Table(name="user", indexes={@ORM\Index(name="search_user_idx", columns={"first_name", "last_name"})})
 */
class User implements UserInterface
{
    public const REGISTERED  = 0;
    public const STUDENT = 1;
    public const INSTRUCTOR = 2;
    public const CREATED = 3;

    use TimeAwareTrait;
    use SecurityTrait;
    use HumanTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"user", "student"})
     */
    private $id;

    /**
     * @var int $type
     *
     * @ORM\Column(name="type", type="smallint", length=2)
     * @Assert\NotBlank()
     * @Assert\Choice(choices={1, 2}, message="Invalid type value. Please set 1 or 2")
     * @Serializer\Groups({"user"})
     */
    private $type = self::STUDENT;

    /**
     * @var mixed $classToken
     *
     * @ORM\Column(name="class_token", type="string", length=40, nullable=true, unique=true)
     */
    private $classToken;

    /**
     * @var mixed $classToken
     *
     * @ORM\Column(name="country", type="string", length=25, nullable=true)
     * @Serializer\Groups({"user"})
     */
    private $country;

    /**
     * @var Test
     *
     * @ORM\OneToMany(targetEntity="Test", mappedBy="instructor", cascade={"persist", "remove"})
     */
    private $test;

    /**
     * @var Blog
     *
     * @ORM\OneToMany(targetEntity="Blog", mappedBy="author", cascade={"persist", "remove"})
     */
    private $blog;

    /**
     * @var StudentClass
     *
     * @Assert\Count(
     *      max = 5,
     *      maxMessage = "You cannot specify more than {{ limit }} class"
     * )
     * @ORM\OneToMany(targetEntity="StudentClass", mappedBy="instructor", cascade={"persist", "remove"})
     */
    private $studentClass;

    /**
     * @var StudentClass
     *
     * @ORM\ManyToOne(targetEntity="StudentClass", inversedBy="student")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_student_class", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $class;

    /**
     * @ORM\OneToMany(targetEntity="AssignTest", mappedBy="student", cascade={"persist", "remove"})
     */
    private $assignTest;

    /**
     * @var Notification
     *
     * @ORM\OneToMany(targetEntity="Notification", mappedBy="user", cascade={"persist", "remove"})
     */
    private $notification;

    /**
     * @ORM\OneToMany(targetEntity="PassedQuestion", mappedBy="student", cascade={"persist", "remove"})
     */
    private $passedQuestion;

    /**
     * @var School
     *
     * @ORM\ManyToOne(targetEntity="School", inversedBy="user", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_school", referencedColumnName="id", onDelete="SET NULL")
     * })
     * @Serializer\Groups({"user"})
     */
    private $school;

    /**
     * @var int $status
     *
     * @ORM\Column(name="status", type="smallint")
     * @Assert\Choice(choices={0, 3}, message="Invalid status value. Please set 0 or 3")
     */
    private $status = self::REGISTERED;

    /**
     * @ORM\OneToOne(targetEntity="App\Stripe\Entity\Subscription", mappedBy="user", cascade={"persist"})
     */
    private $subscription;

    /**
     * @ORM\Column(type="string", unique=true, nullable=true)
     */
    private $stripeCustomerId;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $cardBrand;

    /**
     * @ORM\Column(type="string", length=4, nullable=true)
     */
    private $cardLast4;

    public $isPasswordReset = false;

    /**
     * User constructor.
     */
    public function __construct()
    {
        $this->test = new ArrayCollection();
        $this->studentClass = new ArrayCollection();
        $this->notification = new ArrayCollection();
        $this->assignTest = new ArrayCollection();
        $this->passedQuestion = new ArrayCollection();
        $this->blog = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getFullName() ?? '';
    }

    /**
     * @Serializer\Groups({"user"})
     * @Serializer\VirtualProperty("subscribe_active")
     * @return bool
     */
    public function isActive(): bool
    {
        $subscription = $this->subscription;

        return $subscription && $subscription->getStatus() === Subscription::ACTIVE &&
            ($subscription->getEndsAt() === null || $subscription->getEndsAt() > new DateTime());
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return User
     */
    public function setType($type): self
    {
        $this->type = $type;

        if ($type === self::INSTRUCTOR) {
            $this->setRoles(['ROLE_INSTRUCTOR']);
        }

        if ($type === self::STUDENT) {
            $this->setRoles(['ROLE_STUDENT']);
        }

        return $this;
    }

    /**
     * @return Collection|Test[]
     */
    public function getTest(): Collection
    {
        return $this->test;
    }

    /**
     * @param Test $test
     * @return User
     */
    public function addTest(Test $test): self
    {
        if (!$this->test->contains($test)) {
            $this->test[] = $test;
            $test->setInstructor($this);
        }

        return $this;
    }

    /**
     * @param Test $test
     * @return User
     */
    public function removeTest(Test $test): self
    {
        if ($this->test->contains($test)) {
            $this->test->removeElement($test);
            // set the owning side to null (unless already changed)
            if ($test->getInstructor() === $this) {
                $test->setInstructor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|StudentClass[]
     */
    public function getStudentClass(): Collection
    {
        return $this->studentClass;
    }

    /**
     * @param StudentClass $studentClass
     * @return User
     */
    public function addStudentClass(StudentClass $studentClass): self
    {
        if (!$this->studentClass->contains($studentClass)) {
            $this->studentClass[] = $studentClass;
            $studentClass->setInstructor($this);
        }

        return $this;
    }

    /**
     * @param StudentClass $studentClass
     * @return User
     */
    public function removeStudentClass(StudentClass $studentClass): self
    {
        if ($this->studentClass->contains($studentClass)) {
            $this->studentClass->removeElement($studentClass);
            // set the owning side to null (unless already changed)
            if ($studentClass->getInstructor() === $this) {
                $studentClass->setInstructor(null);
            }
        }

        return $this;
    }

    /**
     * @return StudentClass|null
     */
    public function getClass(): ?StudentClass
    {
        return $this->class;
    }

    /**
     * @param StudentClass|null $class
     * @return User
     */
    public function setClass(?StudentClass $class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @return Collection|Notification[]
     */
    public function getNotification(): Collection
    {
        return $this->notification;
    }

    /**
     * @param Notification $notification
     * @return User
     */
    public function addNotification(Notification $notification): self
    {
        if (!$this->notification->contains($notification)) {
            $this->notification[] = $notification;
            $notification->setUser($this);
        }

        return $this;
    }

    /**
     * @param Notification $notification
     * @return User
     */
    public function removeNotification(Notification $notification): self
    {
        if ($this->notification->contains($notification)) {
            $this->notification->removeElement($notification);
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

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
     * @return User
     */
    public function addAssignTest(AssignTest $assignTest): self
    {
        if (!$this->assignTest->contains($assignTest)) {
            $this->assignTest[] = $assignTest;
            $assignTest->setStudent($this);
        }

        return $this;
    }

    /**
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return User
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param AssignTest $assignTest
     * @return User
     */
    public function removeAssignTest(AssignTest $assignTest): self
    {
        if ($this->assignTest->contains($assignTest)) {
            $this->assignTest->removeElement($assignTest);
            // set the owning side to null (unless already changed)
            if ($assignTest->getStudent() === $this) {
                $assignTest->setStudent(null);
            }
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
     * @return User
     */
    public function addPassedQuestion(PassedQuestion $passedQuestion): self
    {
        if (!$this->passedQuestion->contains($passedQuestion)) {
            $this->passedQuestion[] = $passedQuestion;
            $passedQuestion->setStudent($this);
        }

        return $this;
    }

    /**
     * @param PassedQuestion $passedQuestion
     * @return User
     */
    public function removePassedQuestion(PassedQuestion $passedQuestion): self
    {
        if ($this->passedQuestion->contains($passedQuestion)) {
            $this->passedQuestion->removeElement($passedQuestion);
            // set the owning side to null (unless already changed)
            if ($passedQuestion->getStudent() === $this) {
                $passedQuestion->setStudent(null);
            }
        }

        return $this;
    }

    /**
     * @return null|string
     */
    public function getClassToken(): ?string
    {
        return $this->classToken;
    }

    /**
     * @param string|null $classToken
     * @return User
     */
    public function setClassToken($classToken): self
    {
        $this->classToken = $classToken;

        return $this;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): self
    {
        $this->school = $school;

        return $this;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->getSubscription() && $this->getSubscription()->isActive();
    }

    public function hasActiveNonCancelledSubscription(): bool
    {
        return $this->hasActiveSubscription() && !$this->getSubscription()->isCancelled();
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): self
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    public function getCardBrand(): ?string
    {
        return $this->cardBrand;
    }

    public function setCardBrand(?string $cardBrand): self
    {
        $this->cardBrand = $cardBrand;

        return $this;
    }

    public function getCardLast4(): ?string
    {
        return $this->cardLast4;
    }

    public function setCardLast4(?string $cardLast4): self
    {
        $this->cardLast4 = $cardLast4;

        return $this;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    /**
     * @param Subscription|null $subscription
     * @return $this
     */
    public function setSubscription(?Subscription $subscription): self
    {
        $this->subscription = $subscription;

        // set (or unset) the owning side of the relation if necessary
        $newUser = null === $subscription ? null : $this;
        if ($subscription->getUser() !== $newUser) {
            $subscription->setUser($newUser);
        }

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return Collection|Blog[]
     */
    public function getBlog(): Collection
    {
        return $this->blog;
    }

    public function addBlog(Blog $blog): self
    {
        if (!$this->blog->contains($blog)) {
            $this->blog[] = $blog;
            $blog->setAuthor($this);
        }

        return $this;
    }

    public function removeBlog(Blog $blog): self
    {
        if ($this->blog->contains($blog)) {
            $this->blog->removeElement($blog);
            // set the owning side to null (unless already changed)
            if ($blog->getAuthor() === $this) {
                $blog->setAuthor(null);
            }
        }

        return $this;
    }
}
