<?php

namespace App\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;

/**
 * Class A0User
 * @package App\Security\User
 */
class A0User implements UserInterface, EquatableInterface
{
    /** @var $jwt */
    private $jwt;

    /** @var array */
    private $roles;

    /**
     * A0User constructor.
     * @param $jwt
     * @param array $roles
     */
    public function __construct($jwt, array $roles)
    {
        $this->jwt = $jwt;
        $this->roles = $roles;
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return isset($this->jwt['email']) ?? $this->jwt['sub'];
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @param UserInterface $user
     * @return bool
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        if ($this->getUsername() !== $user->getUsername()) {
            return false;
        }

        return true;
    }
}
