<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\Interfaces\SyncInterface;
use Mautic\IntegrationsBundle\Sync\DAO\Mapping\MappingManualDAO;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\SyncDataExchangeInterface;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;

class SyncSupport extends Pipedrive2Integration implements SyncInterface
{
    public function __construct(private MappingManualFactory $mappingManualFactory, private SyncDataExchangeInterface $syncDataExchange)
    {
    }

    public function getSyncDataExchange(): SyncDataExchangeInterface
    {
        return $this->syncDataExchange;
    }

    public function getMappingManual(): MappingManualDAO
    {
        return $this->mappingManualFactory->getManual();
    }
}
