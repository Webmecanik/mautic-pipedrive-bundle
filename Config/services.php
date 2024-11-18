<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [
        'DependencyInjection',
        'Connection/Credentials.php',
        'Connection/DTO',
        'Sync',
        'Tests',
    ];

    $services->load('MauticPlugin\\PipedriveBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    // Event Listeners
    $services->set(MauticPlugin\PipedriveBundle\EventListener\LeadIntegrationOverrideSubscriber::class)
        ->decorate(Mautic\IntegrationsBundle\EventListener\LeadSubscriber::class);
    $services->set(MauticPlugin\PipedriveBundle\EventListener\LeadSubscriber::class);
    $services->set(MauticPlugin\PipedriveBundle\EventListener\DeleteObjectSubscriber::class);

    // Push Data Subscribers
    $services->set(MauticPlugin\PipedriveBundle\EventListener\PushDataFormSubscriber::class);
    $services->set(MauticPlugin\PipedriveBundle\EventListener\PushDataCampaignSubscriber::class);
    $services->set(MauticPlugin\PipedriveBundle\EventListener\PushDataPointSubscriber::class);
    $services->set(MauticPlugin\PipedriveBundle\EventListener\SyncEventsSubscriber::class)
        ->arg('$syncEvents', new Reference('pipedrive2.sync.events'));

    // Configuration services
    $services->set('pipedrive2.config',MauticPlugin\PipedriveBundle\Integration\Config::class);
    $services->set('pipedrive2.connection.config', MauticPlugin\PipedriveBundle\Connection\Config::class);
    $services->set('pipedrive2.connection.client',MauticPlugin\PipedriveBundle\Connection\Client::class);
    $services->set('pipedrive2.object_mapping.repository', MauticPlugin\PipedriveBundle\Repository\ObjectMappingRepository::class);

    // Client owners and activities
    $services->set('pipedrive2.client.owners', MauticPlugin\PipedriveBundle\Connection\Owners::class);
    $services->set('pipedrive2.client.activities',MauticPlugin\PipedriveBundle\Connection\Activities::class);

    // Sync services
    $services->set('pipedrive2.sync.repository.fields',MauticPlugin\PipedriveBundle\Sync\Mapping\Field\FieldRepository::class);
    $services->set('pipedrive2.sync.mapping_manual.factory',MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory::class)
        ->arg('$fieldRepository', new Reference('pipedrive2.sync.repository.fields'));
    $services->set('pipedrive2.sync.data_exchange', MauticPlugin\PipedriveBundle\Sync\DataExchange\SyncDataExchange::class)
        ->arg('$reportBuilder',  new Reference('pipedrive2.sync.data_exchange.report_builder'))
        ->arg('$orderExecutioner',  new Reference('pipedrive2.sync.data_exchange.order_executioner'));
    $services->set('pipedrive2.sync.data_exchange.report_builder', MauticPlugin\PipedriveBundle\Sync\DataExchange\ReportBuilder::class)
        ->arg('$fieldRepository', new Reference('pipedrive2.sync.repository.fields'));
    $services->set('pipedrive2.sync.data_exchange.order_executioner', MauticPlugin\PipedriveBundle\Sync\DataExchange\OrderExecutioner::class)
        ->arg('$relationSyncToIntegration',  new Reference('pipedrive2.integrations.sync.company.relation.integration'))
        ->arg('$fieldRepository',  new Reference('pipedrive2.sync.repository.fields'));
    $services->set('pipedrive2.integrations.sync.company.relation.integration', MauticPlugin\PipedriveBundle\Sync\Sync\CompanyRelation\CompanyRelationSyncToIntegration::class);

    // Sync events
    $services->set('pipedrive2.sync.events', MauticPlugin\PipedriveBundle\Sync\DataExchange\SyncEvents::class);
};
