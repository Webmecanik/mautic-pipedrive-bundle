<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Exception\InvalidValueException;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\InputOptionsDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\ObjectIdsDAO;
use Mautic\IntegrationsBundle\Sync\SyncService\SyncService;
use Mautic\PointBundle\Event\TriggerBuilderEvent;
use Mautic\PointBundle\Event\TriggerExecutedEvent;
use Mautic\PointBundle\PointEvents;
use MauticPlugin\PipedriveBundle\Form\Type\PushContactActionType;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\PipedriveEvents;
use MauticPlugin\PipedriveBundle\Sync\DataExchange\OrderExecutioner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PushDataPointSubscriber implements EventSubscriberInterface
{
    /**
     * PushDataPointSubscriber constructor.
     */
    public function __construct(private SyncService $syncService, private Config $config)
    {
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            PointEvents::TRIGGER_ON_BUILD                    => ['configureTrigger', 0],
            PipedriveEvents::ON_POINT_TRIGGER_PUSH_CONTACT   => ['pushContacts', 0],
        ];
    }

    public function configureTrigger(TriggerBuilderEvent $event): void
    {
        if ($this->config->isConfigured()) {
            $action = [
                'group'       => 'mautic.plugin.point.action',
                'label'       => 'pipedrive.push.contact',
                'description' => 'pipedrive.push.contact.desc',
                'formType'    => PushContactActionType::class,
                'eventName'   => PipedriveEvents::ON_POINT_TRIGGER_PUSH_CONTACT,
            ];
            $event->addEvent('contact.push_to_pipedrive', $action);
        }
    }

    /**
     * @throws IntegrationNotFoundException
     * @throws InvalidValueException
     */
    public function pushContacts(TriggerExecutedEvent $event): void
    {
        try {
            $mauticObjectIds = new ObjectIdsDAO();
            $mauticObjectIds->addObjectId('lead', (string) $event->getLead()->getId());

            $inputOptions = new InputOptionsDAO(
                [
                    'integration'      => Pipedrive2Integration::NAME,
                    'disable-pull'     => true,
                    'mautic-object-id' => $mauticObjectIds,
                    'options'          => [OrderExecutioner::FORCE_SYNC => true],
                ]
            );
            $this->syncService->processIntegrationSync($inputOptions);
            $event->setSucceded();
        } catch (IntegrationNotFoundException|InvalidValueException) {
            $event->setFailed();
        }
    }
}
