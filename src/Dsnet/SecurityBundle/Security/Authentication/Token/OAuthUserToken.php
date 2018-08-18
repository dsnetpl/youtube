<?php

namespace Dsnet\SecurityBundle\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class OAuthUserToken extends AbstractToken
{
    private $accessToken;

    public function __construct($accessToken)
    {
        parent::__construct(array('ROLE_USER'));

        $this->accessToken = $accessToken;

     //   $this->setAuthenticated(true);
    }

    /**
     * Returns the user credentials.
     *
     * @return mixed The user credentials
     */
    public function getCredentials()
    {
        return '';
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }
}
