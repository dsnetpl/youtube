<?php

namespace AppBundle\Services;

use Buzz\Browser;
use Buzz\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DownloadService
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Browser
     */
    private $browser;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    public function __construct(ContainerInterface $container, Browser $browser, TokenStorageInterface $tokenStorage)
    {
        $this->container = $container;
        $this->browser = $browser;
        $this->tokenStorage = $tokenStorage;
    }

    public function getUser()
    {
        if (!$this->tokenStorage) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_numeric($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    public function getPacketLoss()
    {
        $key = 'network_packet_loss';
        $cache = $this->container->get('doctrine_cache.providers.generic');;
        $loss = 0;
        if ($cache->contains($key)) {
            $loss = $cache->fetch($key);
        } else {
            $res = null;
            try {
                $res = $this->browser->get($this->container->getParameter('loss_provider'));
            } catch (RequestException $e) {
            }
            if ($res) {
                $loss = $res->getContent();
            }
            $cache->save($key, $loss, 60);
        }

        return $loss;
    }

    public function yt_dl($arguments)
    {
        $builder = new ProcessBuilder();
        $builder->setPrefix('youtube-dl');
        $builder->setArguments($arguments);

        return $builder->getProcess();
    }

}
