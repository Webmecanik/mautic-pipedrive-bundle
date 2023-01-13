<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Exception\InvalidValueException;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\InputOptionsDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\ObjectIdsDAO;
use Mautic\IntegrationsBundle\Sync\SyncService\SyncService;
use MauticPlugin\PipedriveBundle\Form\Type\PushContactActionType;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\PipedriveEvents;
use MauticPlugin\PipedriveBundle\Sync\DataExchange\OrderExecutioner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PushDataCampaignSubscriber implements EventSubscriberInterface
{
    /**
     * @var SyncService
     */
    private $syncService;

    /**
     * @var Config
     */
    private $config;

    /**
     * PushDataCampaignSubscriber constructor.
     */
    public function __construct(SyncService $syncService, Config $config)
    {
        $this->syncService        = $syncService;
        $this->config             = $config;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD                  => ['configureAction', 0],
            PipedriveEvents::ON_CAMPAIGN_ACTION_PUSH_CONTACT   => ['pushContacts', 0],
        ];
    }

    public function configureAction(CampaignBuilderEvent $event)
    {
        if ($this->config->isConfigured()) {
            $event->addAction(
                'contact.push_to_pipedrive',
                [
                    'group'          => 'mautic.lead.lead.submitaction',
                    'label'          => 'pipedrive.push.contact',
                    'description'    => 'pipedrive.push.contact.desc',
                    'batchEventName' => PipedriveEvents::ON_CAMPAIGN_ACTION_PUSH_CONTACT,
                    'formType'       => PushContactActionType::class,
                ]
            );
        }
    }

    /**
     * @throws \Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException
     * @throws \Mautic\IntegrationsBundle\Exception\InvalidValueException
     */
    public function pushContacts(PendingEvent $event)
    {
        $contactIds = $event->getContactIds();
        try {
            $mauticObjectIds = new ObjectIdsDAO();
            foreach ($contactIds as $contactId) {
                $mauticObjectIds->addObjectId('lead', ''.$contactId);
            }

            $inputOptions = new InputOptionsDAO(
                [
                    'integration'      => Pipedrive2Integration::NAME,
                    'disable-pull'     => true,
                    'mautic-object-id' => $mauticObjectIds,
                    'options'          => [OrderExecutioner::FORCE_SYNC => true],
                ]
            );

            $this->syncService->processIntegrationSync($inputOptions);
            $event->passAll();
        } catch (IntegrationNotFoundException $integrationNotFoundException) {
            $event->failAll($integrationNotFoundException->getMessage());
        } catch (InvalidValueException $invalidValueException) {
            $event->failAll($invalidValueException->getMessage());
        }
    }
}
