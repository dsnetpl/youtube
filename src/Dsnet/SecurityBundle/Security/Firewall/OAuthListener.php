<?php

namespace Dsnet\SecurityBundle\Security\Firewall;

use Dsnet\SecurityBundle\Security\Authentication\Token\OAuthUserToken;
use Dsnet\SecurityBundle\Services\PanelResourceOwner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;

class OAuthListener implements ListenerInterface
{
    protected $securityContext;
    protected $authenticationManager;
    protected $flashBag;
    protected $resourceOwner;

    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, PanelResourceOwner $resourceOwner, FlashBagInterface $flashBag)
    {
        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->resourceOwner = $resourceOwner;
        $this->flashBag = $flashBag;
    }

    /**
     * This interface must be implemented by firewall listeners.
     *
     * @param GetResponseEvent $event
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        if (!$request->query->has('code')) {
            return;
        }
        $code = $request->query->get('code');
        try {
            $accessToken = $this->resourceOwner->getAccessToken($code);
            $token = new OAuthUserToken($accessToken['access_token']);
            $authToken = $this->authenticationManager->authenticate($token);
            $this->securityContext->setToken($authToken);
            $response = new RedirectResponse('/');
            $event->setResponse($response);

            return;
        } catch (AuthenticationException $failed) {
            $this->flashBag->add('error', $failed->getMessage());
            $response = new RedirectResponse($request->getPathInfo());
            $event->setResponse($response);

            return;
        }
    }
}
