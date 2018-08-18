<?php

namespace Dsnet\SecurityBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

class SecurityController extends Controller
{
    /**
     * @Route("/login")
     */
    public function loginAction()
    {
        return $this->redirect($this->get('dsnet_security.panel_resource_owner')->getAuthorizationUrl());
    }

    /**
     * @Route("/login_check", name="login_check")
     */
    public function loginCheckAction()
    {
        return new Response();
    }
}
