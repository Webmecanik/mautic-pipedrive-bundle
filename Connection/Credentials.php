<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Connection;

use Mautic\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\Credentials\CodeInterface;
use Mautic\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\Credentials\CredentialsInterface;
use Mautic\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\Credentials\RedirectUriInterface;
use Mautic\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\Credentials\ScopeInterface;
use Mautic\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\Credentials\StateInterface;
use MauticPlugin\PipedriveBundle\Enum\SettingsEnum;

class Credentials implements CredentialsInterface, CodeInterface, StateInterface, RedirectUriInterface, ScopeInterface
{
    private string $authorizationUrl;

    private string $tokenUrl;

    private string $redirectUri;

    private ?string $clientId;

    private ?string $clientSecret;

    private ?string $code;

    private ?string $state;

    public function __construct(string $tokenUrl, string $redirectUri, string $clientId, string $clientSecret, ?string $code, ?string $state)
    {
        $this->tokenUrl         = $tokenUrl;
        $this->redirectUri      = $redirectUri;
        $this->clientId         = $clientId;
        $this->clientSecret     = $clientSecret;
        $this->code             = $code;
        $this->state            = $state;
        $this->authorizationUrl = $this->getAuthorizeUrl();
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getScope(): ?string
    {
        return 'api';
    }

    private function getAuthorizeUrl(): string
    {
        return SettingsEnum::PIPEDRIVE_AUTH_URL.
            '?client_id='.$this->clientId.
            '&state='.$this->state.
            '&redirect_uri='.urlencode($this->redirectUri);
    }
}
