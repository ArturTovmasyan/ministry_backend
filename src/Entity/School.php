<?php

namespace App\Entity;

use App\Entity\Traits\TimeAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="school")
 * @ORM\Entity(repositoryClass="App\Repository\SchoolRepository")
 * @UniqueEntity(fields={"name"}, message="School with this name already exist.")
 */
class School
{
    use TimeAwareTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"user"})
     */
    private $id;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     * @Assert\NotBlank()
     * @Serializer\Groups({"user"})
     */
    private $name;

    /**
     * @var User
     *
     * @ORM\OneToMany(targetEntity="User", mappedBy="school", cascade={"persist"})
     */
    private $user;

    /**
     * @ORM\Column(name="logo", type="string", length=255)
     * @Assert\Url(message="Invalid school logo url")
     * @Assert\NotBlank()
     * @Serializer\Groups({"user"})
     */
    private $logo;

    public function __construct()
    {
        $this->user = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUser(): Collection
    {
        return $this->user;
    }

    public function addUser(User $user): self
    {
        if (!$this->user->contains($user)) {
            $this->user[] = $user;
            $user->setSchool($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->user->contains($user)) {
            $this->user->removeElement($user);
            // set the owning side to null (unless already changed)
            if ($user->getSchool() === $this) {
                $user->setSchool(null);
            }
        }

        return $this;
    }
}
