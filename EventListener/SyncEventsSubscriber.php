<?php

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Event\SyncEvent;
use Mautic\IntegrationsBundle\IntegrationEvents;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Sync\DataExchange\SyncEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SyncEventsSubscriber implements EventSubscriberInterface
{
    public function __construct(private Config $config, private SyncEvents $syncEvents)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            IntegrationEvents::INTEGRATION_POST_EXECUTE => ['onIntegrationPostExecute'],
        ];
    }

    public function onIntegrationPostExecute(SyncEvent $syncEvent): void
    {
        $this->syncEvents($syncEvent);
    }

    protected function syncEvents(SyncEvent $syncEvent): void
    {
        if (!$this->config->shouldSyncActivities()) {
            return;
        }

        $inputOptionsDAO = $syncEvent->getInputOptions();

        if (!$inputOptionsDAO->activityPushIsEnabled()) {
            return;
        }

        if (!$inputOptionsDAO->pushIsEnabled()) {
            return;
        }

        $startDateTime   = $inputOptionsDAO->getStartDateTime();

        // sync just full for first time or by date range
        if (!$startDateTime && !$inputOptionsDAO->isFirstTimeSync()) {
            return;
        }
        $endDateTime   = $inputOptionsDAO->getEndDateTime() ?: new \DateTime('now', new \DateTimeZone('UTC'));
        $this->syncEvents->sync($startDateTime, $endDateTime);
    }
}
