<?php

namespace App\Security\User;

/**
 * Class A0AnonymousUser
 * @package AppBundle\Security\User
 */
class A0AnonymousUser extends A0User {

    /**
     * A0AnonymousUser constructor.
     */
    public function __construct()
    {
        parent::__construct(null, array('IS_AUTHENTICATED_ANONYMOUSLY'));
    }

    /**
     * Get username
     */
    public function getUsername()
    {
        return null;
    }

} 