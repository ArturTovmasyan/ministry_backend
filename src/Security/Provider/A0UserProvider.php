<?php

namespace App\Security\Provider;

use App\Security\User\A0AnonymousUser;
use App\Security\User\A0User;
use Auth0\JWTAuthBundle\Security\Auth0Service;
use Auth0\JWTAuthBundle\Security\Core\JWTUserProviderInterface;
use Gedmo\Exception\FeatureNotImplementedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

/**
 * Class A0UserProvider
 * @package App\Security\Provider
 */
class A0UserProvider implements JWTUserProviderInterface
{
    /** @var Auth0Service  */
    protected $auth0Service;

    /**
     * A0UserProvider constructor.
     * @param Auth0Service $auth0Service
     */
    public function __construct(Auth0Service $auth0Service)
    {
        $this->auth0Service = $auth0Service;
    }

    /**
     * @param object $jwt
     * @return A0User
     */
    public function loadUserByJWT($jwt): A0User
    {
        $data = ['sub' => $jwt->sub];
        $roles = ['ROLE_OAUTH_AUTHENTICATED'];

        if (isset($jwt->scope)) {
            $scopes = explode(' ', $jwt->scope);

            if (\in_array('read:messages', $scopes, false) !== false) {
                $roles[] = 'ROLE_OAUTH_READER';
            }
        }

        return new A0User($data, $roles);
    }

    /**
     * @return A0AnonymousUser
     */
    public function getAnonymousUser(): A0AnonymousUser
    {
        return new A0AnonymousUser();
    }

    /**
     * @param UserInterface $user
     * @return UserInterface|void
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof A0User) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', \get_class($user))
            );
        }

         $this->loadUserByUsername($user->getUsername());
    }

    public function loadUserByUsername($username): void
    {
        throw new FeatureNotImplementedException('method not implemented');
    }

    /**
     * @param string $class
     * @return bool
     */
    public function supportsClass($class): bool
    {
        return $class === 'App\Security\User\A0User';
    }
}
