<?php

namespace Dsnet\SecurityBundle\Security\Authentication\Provider;

use Doctrine\ORM\EntityManager;
use Dsnet\SecurityBundle\Security\Authentication\Token\OAuthUserToken;
use Dsnet\SecurityBundle\Services\PanelResourceOwner;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class OAuthProvider implements AuthenticationProviderInterface
{
    private $userProvider;
    private $resourceOwner;
    private $em;

    public function __construct(UserProviderInterface $userProvider, PanelResourceOwner $resourceOwner, EntityManager $em)
    {
        $this->userProvider = $userProvider;
        $this->resourceOwner = $resourceOwner;
        $this->em = $em;
    }

    /**
     * Attempts to authenticate a TokenInterface object.
     *
     * @param TokenInterface $token The TokenInterface instance to authenticate
     *
     * @return TokenInterface An authenticated TokenInterface instance, never null
     *
     * @throws AuthenticationException if the authentication fails
     */
    public function authenticate(TokenInterface $token)
    {
        $userInfo = $this->resourceOwner->getUserInfo($token->getAccessToken());
        $token->setAuthenticated(true);
        $token->setUser((string) $userInfo['tenant_id']);

        return $token;
    }

    /**
     * Checks whether this provider supports the given token.
     *
     * @param TokenInterface $token A TokenInterface instance
     *
     * @return bool true if the implementation supports the Token, false otherwise
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof OAuthUserToken;
    }
}
