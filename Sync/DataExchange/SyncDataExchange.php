<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\DataExchange;

use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\OrderDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Report\ReportDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Request\RequestDAO;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\SyncDataExchangeInterface;

class SyncDataExchange implements SyncDataExchangeInterface
{
    /**
     * SyncDataExchange constructor.
     */
    public function __construct(private ReportBuilder $reportBuilder, private OrderExecutioner $orderExecutioner)
    {
    }

    public function getSyncReport(RequestDAO $requestDAO): ReportDAO
    {
        return $this->reportBuilder->build(
            $requestDAO->getSyncIteration(),
            $requestDAO->getObjects(),
            $requestDAO->getInputOptionsDAO()
        );
    }

    public function executeSyncOrder(OrderDAO $syncOrderDAO): void
    {
        $this->orderExecutioner->execute($syncOrderDAO);
    }
}
