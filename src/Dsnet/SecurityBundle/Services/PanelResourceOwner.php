<?php

namespace Dsnet\SecurityBundle\Services;

use Buzz\Browser;
use Buzz\Message\MessageInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class PanelResourceOwner
{
    const AUTHORIZATION_ENDPOINT = '/oauth/v2/auth';
    const TOKEN_ENDPOINT = '/oauth/v2/token';
    const API_URL = '/api';

    private $browser;
    private $options;
    private $router;

    public function __construct(Browser $browser, Router $router, array $options)
    {
        $this->browser = $browser;
        $this->options = $options;
        $this->router = $router;
    }

    public function getAuthorizationUrl()
    {
        $params = array(
            'response_type' => 'code',
            'client_id' => $this->options['client_id'],
            'redirect_uri' => $this->router->generate($this->options['redirect_uri'], array(), UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $url = $this->options['panel_base_url'].self::AUTHORIZATION_ENDPOINT.'?'.http_build_query($params, null, '&');

        return $url;
    }

    public function getAccessToken($code)
    {
        $parameters = array(
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $this->options['client_id'],
            'client_secret' => $this->options['client_secret'],
            'redirect_uri' => $this->router->generate($this->options['redirect_uri'], array(), UrlGeneratorInterface::ABSOLUTE_URL),
        );
        $response = $this->browser->get($this->options['panel_base_url'].self::TOKEN_ENDPOINT.'?'.http_build_query($parameters, null, '&'));
        $response = $this->getResponseContent($response);

        $this->validateResponse($response);

        return $response;
    }

    public function getUserInfo($accessToken)
    {
        $parameters = array(
            'access_token' => $accessToken,
            'client_id' => $this->options['client_id'],
            'client_secret' => $this->options['client_secret'],
            'redirect_uri' => $this->router->generate($this->options['redirect_uri'], array(), UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $response = $this->browser->get($this->options['panel_base_url'].self::API_URL.'/beta/tenant/user'.'?'.http_build_query($parameters, null, '&'));
        $response = $this->getResponseContent($response);

        $this->validateResponse($response, false);

        $response2 = $this->browser->get($this->options['panel_base_url'].self::API_URL.'/beta/tenant/personal'.'?'.http_build_query($parameters, null, '&'));
        $response2 = $this->getResponseContent($response2);

        $this->validateResponse($response2, false);

        return array_merge($response, $response2);
    }

    private function validateResponse($response, $accessToken = true)
    {
        if (isset($response['error_description'])) {
            throw new AuthenticationException(sprintf('OAuth error: "%s"', $response['error_description']));
        }

        if (isset($response['error'])) {
            throw new AuthenticationException(sprintf('OAuth error: "%s"', isset($response['error']['message']) ? $response['error']['message'] : $response['error']));
        }

        if ($accessToken && !isset($response['access_token'])) {
            throw new AuthenticationException('Not a valid access token.');
        }
    }

    private function getResponseContent(MessageInterface $rawResponse)
    {
        $content = $rawResponse->getContent();
        if (!$content) {
            return array();
        }

        $response = json_decode($content, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            parse_str($content, $response);
        }

        return $response;
    }
}
