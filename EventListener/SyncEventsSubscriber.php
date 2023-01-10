<?php

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Event\SyncEvent;
use Mautic\IntegrationsBundle\IntegrationEvents;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Sync\DataExchange\SyncEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SyncEventsSubscriber implements EventSubscriberInterface
{
    private Config $config;

    private SyncEvents $syncEvents;

    public function __construct(Config $config, SyncEvents $syncEvents)
    {
        $this->config     = $config;
        $this->syncEvents = $syncEvents;
    }

    public static function getSubscribedEvents()
    {
        return [
            IntegrationEvents::INTEGRATION_POST_EXECUTE => ['onIntegrationPostExecute'],
        ];
    }

    public function onIntegrationPostExecute(SyncEvent $syncEvent)
    {
        $this->syncEvents($syncEvent);
    }

    protected function syncEvents(SyncEvent $syncEvent): void
    {
        if (!$this->config->shouldSyncActivities()) {
            return;
        }

        $inputOptionsDAO = $syncEvent->getInputOptionsDAO();
        $startDateTime   = $inputOptionsDAO->getStartDateTime();

        if (!$inputOptionsDAO->pushIsEnabled()) {
            return;
        }

        // sync just full for first time or by date range
        if (!$startDateTime && !$inputOptionsDAO->isFirstTimeSync()) {
            return;
        }
        $endDateTime   = $inputOptionsDAO->getEndDateTime() ?: new \DateTime('now', new \DateTimeZone('UTC'));
        $this->syncEvents->sync($startDateTime, $endDateTime);
    }
}
