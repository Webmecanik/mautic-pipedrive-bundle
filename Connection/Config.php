<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Connection;

use kamermans\OAuth2\Persistence\TokenPersistenceInterface;
use Mautic\IntegrationsBundle\Auth\Support\Oauth2\ConfigAccess\ConfigTokenFactoryInterface;
use Mautic\IntegrationsBundle\Auth\Support\Oauth2\ConfigAccess\ConfigTokenPersistenceInterface;
use Mautic\IntegrationsBundle\Auth\Support\Oauth2\Token\IntegrationTokenFactory;
use Mautic\IntegrationsBundle\Auth\Support\Oauth2\Token\TokenFactoryInterface;
use Mautic\IntegrationsBundle\Auth\Support\Oauth2\Token\TokenPersistenceFactory;
use Mautic\PluginBundle\Entity\Integration;

class Config implements ConfigTokenPersistenceInterface, ConfigTokenFactoryInterface
{
    private \Mautic\IntegrationsBundle\Auth\Support\Oauth2\Token\TokenPersistenceFactory $tokenPersistenceFactory;

    private ?\Mautic\PluginBundle\Entity\Integration $integrationConfiguration = null;

    public function __construct(TokenPersistenceFactory $tokenPersistenceFactory)
    {
        $this->tokenPersistenceFactory  = $tokenPersistenceFactory;
    }

    public function getTokenPersistence(): TokenPersistenceInterface
    {
        return $this->tokenPersistenceFactory->create($this->integrationConfiguration);
    }

    public function getTokenFactory(): TokenFactoryInterface
    {
        return new IntegrationTokenFactory();
    }

    public function setIntegrationConfiguration(Integration $integrationConfiguration): void
    {
        $this->integrationConfiguration = $integrationConfiguration;
    }
}
