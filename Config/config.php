<?php

declare(strict_types=1);

return [
    'name'        => 'Pipedrive 2',
    'description' => 'Pipedrive 2 plugin built on the IntegrationsBundle plugin',
    'version'     => '1.0.0',
    'author'      => 'Webmecanik',
    'routes'      => [
        'main'   => [],
        'public' => [
            'pipedrive2.webhook' => [
                'path'       => '/pipedrive2/webhook',
                'controller' => 'MauticPlugin\PipedriveBundle\Controller\PipedriveController::webhookAction',
                'method'     => 'POST',
            ],
        ],
        'api'    => [],
    ],
    'menu'        => [],
    'services'    => [
        'integrations' => [
            // Basic definitions with name, display name and icon
            'mautic.integration.pipedrive2'        => [
                'class' => \MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            // Provides the form types to use for the configuration UI
            'pipedrive2.integration.configuration' => [
                'class'     => \MauticPlugin\PipedriveBundle\Integration\Support\ConfigSupport::class,
                'arguments' => [
                    'pipedrive2.sync.repository.fields',
                    'pipedrive2.config',
                    'session',
                    'router',
                    'translator',
                ],
                'tags'      => [
                    'mautic.config_integration',
                ],
            ],
            // Defines the mapping manual and sync data exchange service for the sync engine
            'pipedrive2.integration.sync'          => [
                'class'     => \MauticPlugin\PipedriveBundle\Integration\Support\SyncSupport::class,
                'arguments' => [
                    'pipedrive2.sync.mapping_manual.factory',
                    'pipedrive2.sync.data_exchange',
                ],
                'tags'      => [
                    'mautic.sync_integration',
                ],
            ],
            // Provides the means to exchange a code for a token for the oauth2 authorization code grant
            'pipedrive2.integration.authorization' => [
                'class'     => \MauticPlugin\PipedriveBundle\Integration\Support\AuthSupport::class,
                'arguments' => [
                    'pipedrive2.connection.client',
                    'pipedrive2.config',
                    'session',
                    'translator',
                ],
                'tags'      => [
                    'mautic.authentication_integration',
                ],
            ],
        ],
    ]
];
