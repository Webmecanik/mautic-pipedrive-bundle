<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Integration\Support;

use Mautic\IntegrationsBundle\Exception\UnauthorizedException;
use Mautic\IntegrationsBundle\Integration\Interfaces\AuthenticationInterface;
use MauticPlugin\PipedriveBundle\Connection\Client;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\TranslatorInterface;

class AuthSupport extends Pipedrive2Integration implements AuthenticationInterface
{
    private \MauticPlugin\PipedriveBundle\Connection\Client $client;

    private \MauticPlugin\PipedriveBundle\Integration\Config $config;

    private \Symfony\Component\HttpFoundation\Session\Session $session;

    private \Symfony\Component\Translation\TranslatorInterface $translator;

    public function __construct(Client $client, Config $config, Session $session, TranslatorInterface $translator)
    {
        $this->client     = $client;
        $this->config     = $config;
        $this->session    = $session;
        $this->translator = $translator;
    }

    public function isAuthenticated(): bool
    {
        return $this->config->isAuthorized();
    }

    /**
     * @throws UnauthorizedException
     */
    public function authenticateIntegration(Request $request): string
    {
        $code  = $request->get('code');
        $state = $request->get('state');

        $this->validateState($state);

        $this->client->exchangeCodeForToken($code, $state);

        return $this->translator->trans('pipedrive.auth.success');
    }

    /**
     * @throws UnauthorizedException
     */
    private function validateState(string $givenState): void
    {
        // Fetch the state stored in ConfigSupport::getAuthorizationUrl()
        $expectedState = $this->session->get('pipedrive.state');

        // Clear the state
        $this->session->remove('pipedrive.state');

        // Validate the state
        if (!$expectedState || $expectedState !== $givenState) {
            throw new UnauthorizedException('State does not match what was expected');
        }
    }
}
