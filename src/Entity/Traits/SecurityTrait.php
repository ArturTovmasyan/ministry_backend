<?php

namespace App\Entity\Traits;

use App\Components\Helper\JsonHelper;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

trait SecurityTrait
{
    /**
     * @var string $confirmToken
     *
     * @ORM\Column(name="confirm_token", type="string", length=40, nullable=true, unique=true)
     */
    private $confirmToken;

    /**
     * @var string $password
     *
     * @ORM\Column(name="password", type="string", length=100)
     * @Assert\NotNull()
     * @Assert\Length(
     *      min = 6,
     *      max = 100,
     *      minMessage = "Password cannot be less than {{ limit }} characters",
     *      maxMessage = "Password cannot be longer than {{ limit }} characters",
     * )
     */
    private $password = '';

    /**
     * @var string $plainPassword
     * @Assert\Length(
     *      min = 6,
     *      max = 20,
     *      minMessage = "Password cannot be less than {{ limit }} characters",
     *      maxMessage = "Password cannot be longer than {{ limit }} characters",
     * )
     */
    private $plainPassword = '';

    /**
     * @var bool $enabled
     *
     * @ORM\Column(name="is_enabled", type="boolean")
     */
    private $enabled = true;

    /**
     * @var string $salt
     *
     * @ORM\Column(name="salt", type="string", length=100)
     *
     */
    private $salt = '';

    /**
     * @var string $roles
     * @ORM\Column(name="roles", type="string", length=120, nullable=false)
     * @Assert\Length(
     *      max = 120,
     *      maxMessage = "Roles cannot be longer than {{ limit }} characters"
     * )
     */
    private $roles = '[]';

    /**
     * @param array $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = json_encode($roles);
    }

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('ROLE_USER');
     * }
     * </code>
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return array
     */
    public function getRoles(): array
    {
        return json_decode($this->roles, true);
    }

    /**
     * @param string $role
     */
    public function addRole($role): void
    {
        $roles = $this->getRoles();
        if (!$this->hasRole($role)) {
            $roles[] = $role;
        }

        $this->setRoles($roles);
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole($role): bool
    {
        return \in_array($role, $this->getRoles(), true);
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return self
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt(): string
    {
        if (!$this->salt) {
            $randomString = JsonHelper::generateCode(10);
            $this->salt = $randomString;
        }

        return $this->salt;
    }

    /**
     * @return string
     */
    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }

    /**
     * @param string $plainPassword
     */
    public function setPlainPassword($plainPassword): void
    {
        if (!$plainPassword) {
            return;
        }

        // Encode here.
        $passwordEncoded = $this->encodePassword($plainPassword, $this->getSalt());
        $this->plainPassword = $plainPassword;
        $this->password = $passwordEncoded;
    }

    /**
     * @param string $raw
     * @param string $salt
     * @return string
     */
    public function encodePassword($raw, $salt): string
    {
        $messageDigest = new MessageDigestPasswordEncoder();
        return $messageDigest->encodePassword($raw, $salt);
    }

    /**
     * @return null|string
     */
    public function getConfirmToken(): ?string
    {
        return $this->confirmToken;
    }

    /**
     * @param null|string $confirmToken
     * @return self
     */
    public function setConfirmToken(?string $confirmToken): self
    {
        $this->confirmToken = $confirmToken;

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @param string $salt
     * @return self
     */
    public function setSalt(string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }
}