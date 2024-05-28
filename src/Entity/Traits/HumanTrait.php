<?php

namespace App\Entity\Traits;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

trait HumanTrait
{
    /**
     * @var string $name
     * @ORM\Column(name="first_name", type="string", length=40, nullable=true)
     * @Assert\Length(
     *      max = 40,
     *      maxMessage = "FirstName cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"user"})
     */
    private $firstName;

    /**
     * @var string $name
     * @ORM\Column(name="last_name", type="string", length=40, nullable=true)
     * @Assert\Length(
     *      max = 40,
     *      maxMessage = "LastName cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"user"})
     */
    private $lastName;

    /**
     * @var string $email
     *
     * @ORM\Column(name="email", type="string", length=40, unique=true)
     * @Assert\NotBlank()
     * @Assert\Email()
     * @Assert\Length(
     *      max = 40,
     *      maxMessage = "Email cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"user", "student"})
     */
    private $email;

    /**
     * @var string $username
     *
     * @ORM\Column(name="username", type="string", length=40, unique=true)
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 40,
     *      maxMessage = "Username cannot be longer than {{ limit }} characters"
     * )
     */
    private $username;

    /**
     * @return string
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     */
    public function setFirstName($firstName): void
    {
        $firstName = preg_replace('/\s\s+/', ' ', $firstName);
        $this->firstName = $firstName;
    }

    /**
     * @return string
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     */
    public function setLastName(string $lastName): void
    {
        $lastName = preg_replace('/\s\s+/', ' ', $lastName);
        $this->lastName = $lastName;
    }

    /**
     * @return string
     *
     * @Serializer\Groups({"user", "blog"})
     * @Serializer\VirtualProperty()
     */
    public function getFullName(): ?string
    {
        if ($this->firstName && $this->lastName) {
            $fullName = $this->firstName . ' ' . $this->lastName;
        } else {
            $fullName = $this->email;
        }

        return  $fullName;
    }

    /**
     * @return null|string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        $this->username = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $username
     * @return self
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }
}