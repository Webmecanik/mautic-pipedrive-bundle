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
        ],
        'api'    => [],
    ],
    'menu'        => [],
    'services'    => [
        'events'       => [
            'pipedrive2.lead.subscriber'              => [
                'class'     => \MauticPlugin\PipedriveBundle\EventListener\LeadSubscriber::class,
                'arguments' => [
                    'mautic.integrations.repository.field_change',
                    'pipedrive2.object_mapping.repository',
                    'pipedrive2.config',
                ],
            ],
            'pipedrive2.delete.object.subscriber'     => [
                'class'     => \MauticPlugin\PipedriveBundle\EventListener\DeleteObjectSubscriber::class,
                'arguments' => [
                    'pipedrive2.connection.client',
                    'pipedrive2.object_mapping.repository',
                    'mautic.integrations.repository.field_change',
                    'mautic.lead.model.lead',
                    'pipedrive2.config',
                    'translator',
                ],
            ],
            'pipedrive.push_data.form.subscriber'     => [
                'class'     => \MauticPlugin\PipedriveBundle\EventListener\PushDataFormSubscriber::class,
                'arguments' => [
                    'mautic.integrations.sync.service',
                    'pipedrive2.config',
                ],
            ],
            'pipedrive.push_data.campaign.subscriber' => [
                'class'     => \MauticPlugin\PipedriveBundle\EventListener\PushDataCampaignSubscriber::class,
                'arguments' => [
                    'mautic.integrations.sync.service',
                    'pipedrive2.config',
                ],
            ],
            'pipedrive.push_data.point.subscriber'    => [
                'class'     => \MauticPlugin\PipedriveBundle\EventListener\PushDataPointSubscriber::class,
                'arguments' => [
                    'mautic.integrations.sync.service',
                    'pipedrive2.config',
                ],
            ],
            'pipedrive.sync.events.subscriber'    => [
                'class'     => \MauticPlugin\PipedriveBundle\EventListener\SyncEventsSubscriber::class,
                'arguments' => [
                    'pipedrive2.config',
                    'pipedrive2.sync.events',
                ],
            ],
        ],
        'other'        => [
            // Provides access to configured API keys, settings, field mapping, etc
            'pipedrive2.config'                    => [
                'class'     => \MauticPlugin\PipedriveBundle\Integration\Config::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
            // Configuration for the http client which includes where to persist tokens
            'pipedrive2.connection.config'         => [
                'class'     => \MauticPlugin\PipedriveBundle\Connection\Config::class,
                'arguments' => [
                    'mautic.integrations.auth_provider.token_persistence_factory',
                ],
            ],
            // The http client used to communicate with the integration which in this case uses OAuth2 client_credentials grant
            'pipedrive2.connection.client'         => [
                'class'     => \MauticPlugin\PipedriveBundle\Connection\Client::class,
                'arguments' => [
                    'mautic.integrations.auth_provider.oauth2threelegged',
                    'pipedrive2.config',
                    'pipedrive2.connection.config',
                    'monolog.logger.mautic',
                    'router',
                ],
            ],
            'pipedrive2.object_mapping.repository' => [
                'class'     => \MauticPlugin\PipedriveBundle\Repository\ObjectMappingRepository::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
            'pipedrive2.client.owners'             => [
                'class'     => \MauticPlugin\PipedriveBundle\Connection\Owners::class,
                'arguments' => [
                    'pipedrive2.connection.client',
                    'mautic.user.model.user',
                ],
            ],
            'pipedrive2.client.activities'         => [
                'class'     => \MauticPlugin\PipedriveBundle\Connection\Activities::class,
                'arguments' => [
                    'pipedrive2.connection.client',
                    'monolog.logger.mautic',
                ],
            ],
            'pipedrive2.integrations.sync.company.relation.integration' => [
                'class'     => \MauticPlugin\PipedriveBundle\Sync\Sync\CompanyRelation\CompanyRelationSyncToIntegration::class,
                'arguments' => [
                    'mautic.lead.model.company',
                    'mautic.integrations.sync.service',
                    'mautic.integrations.repository.object_mapping',
                ],
            ],
        ],
        'sync'         => [
            // Returns available fields from the integration either from cache or "live" via API
            'pipedrive2.sync.repository.fields'               => [
                'class'     => \MauticPlugin\PipedriveBundle\Sync\Mapping\Field\FieldRepository::class,
                'arguments' => [
                    'mautic.helper.cache_storage',
                    'pipedrive2.connection.client',
                ],
            ],
            // Creates the instructions to the sync engine for which objects and fields to sync and direction of data flow
            'pipedrive2.sync.mapping_manual.factory'          => [
                'class'     => \MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory::class,
                'arguments' => [
                    'pipedrive2.sync.repository.fields',
                    'pipedrive2.config',
                ],
            ],
            // Proxies the actions of the sync between Mautic and this integration to the appropriate services
            'pipedrive2.sync.data_exchange'                   => [
                'class'     => \MauticPlugin\PipedriveBundle\Sync\DataExchange\SyncDataExchange::class,
                'arguments' => [
                    'pipedrive2.sync.data_exchange.report_builder',
                    'pipedrive2.sync.data_exchange.order_executioner',
                ],
            ],
            // Builds a report of updated and new objects from the integration to sync with Mautic
            'pipedrive2.sync.data_exchange.report_builder'    => [
                'class'     => \MauticPlugin\PipedriveBundle\Sync\DataExchange\ReportBuilder::class,
                'arguments' => [
                    'pipedrive2.connection.client',
                    'pipedrive2.config',
                    'pipedrive2.sync.repository.fields',
                    'pipedrive2.client.owners',
                ],
            ],
            // Pushes updated or new Mautic contacts or companies to the integration
            'pipedrive2.sync.data_exchange.order_executioner' => [
                'class'     => \MauticPlugin\PipedriveBundle\Sync\DataExchange\OrderExecutioner::class,
                'arguments' => [
                    'pipedrive2.connection.client',
                    'pipedrive2.integrations.sync.company.relation.integration',
                    'monolog.logger.mautic',
                    'pipedrive2.sync.repository.fields',
                    'pipedrive2.config',
                    'pipedrive2.client.owners',
                ],
            ],
            'pipedrive2.sync.events' => [
                'class'     => \MauticPlugin\PipedriveBundle\Sync\DataExchange\SyncEvents::class,
                'arguments' => [
                    'pipedrive2.object_mapping.repository',
                    'mautic.lead.model.lead',
                    'doctrine.orm.entity_manager',
                    'pipedrive2.config',
                    'pipedrive2.client.activities',
                ],
            ],
        ],
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
        // These are all mocks to simply enable demonstration of the oauth2 flow
        'controllers'  => [],
        'forms'        => [
            'pipdrive2.config_feature_type' => [
                'class'     => \MauticPlugin\PipedriveBundle\Form\Type\ConfigFeaturesType::class,
                'arguments' => [
                    'pipedrive2.config',
                ],
            ],
        ],
    ],
];
