<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
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

class PushDataFormSubscriber implements EventSubscriberInterface
{
    /**
     * @var SyncService
     */
    private $syncService;

    /**
     * @var Config
     */
    private $config;

    public function __construct(SyncService $syncService, Config $config)
    {
        $this->syncService = $syncService;
        $this->config      = $config;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::FORM_ON_BUILD                    => ['configureAction', 0],
            PipedriveEvents::ON_FORM_ACTION_PUSH_CONTACT => ['pushContacts', 0],
        ];
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function configureAction(FormBuilderEvent $event)
    {
        if ($this->config->isConfigured()) {
            $action = [
                'group'             => 'mautic.plugin.actions',
                'label'             => 'pipedrive.push.contact',
                'description'       => 'pipedrive.push.contact.desc',
                'formType'          => PushContactActionType::class,
                'eventName'         => PipedriveEvents::ON_FORM_ACTION_PUSH_CONTACT,
                'allowCampaignForm' => true,
            ];
            $event->addSubmitAction('contact.push_to_pipedrive', $action);
        }
    }

    public function pushContacts(SubmissionEvent $event)
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
        } catch (IntegrationNotFoundException $integrationNotFoundException) {
        } catch (InvalidValueException $invalidValueException) {
        }
    }
}
