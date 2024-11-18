<?php

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Entity\FieldChangeRepository;
use Mautic\IntegrationsBundle\Event\SyncEvent;
use Mautic\IntegrationsBundle\IntegrationEvents;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\PipedriveBundle\Connection\Client;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Repository\ObjectMappingRepository;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeleteObjectSubscriber implements EventSubscriberInterface
{
    private Client $client;

    private Config $config;

    private FieldChangeRepository $fieldChangeRepository;

    private LeadModel $leadModel;

    private ObjectMappingRepository $objectMappingRepository;

    private TranslatorInterface $translator;

    public function __construct(Client $client, ObjectMappingRepository $objectMappingRepository, FieldChangeRepository $fieldChangeRepository, LeadModel $leadModel, Config $config, TranslatorInterface $translator)
    {
        $this->client                  = $client;
        $this->objectMappingRepository = $objectMappingRepository;
        $this->fieldChangeRepository   = $fieldChangeRepository;
        $this->leadModel               = $leadModel;
        $this->config                  = $config;
        $this->translator              = $translator;
    }

    public static function getSubscribedEvents()
    {
        return [
            IntegrationEvents::INTEGRATION_POST_EXECUTE => ['onIntegrationPostExecute'],
        ];
    }

    public function onIntegrationPostExecute(SyncEvent $syncEvent)
    {
        if (!$this->config->isPublished() || !$this->config->shouldDelete()) {
            return;
        }

        // If not sync for date range, skip
        if (!$syncEvent->getFromDateTime() || $syncEvent->getInputOptions()->isFirstTimeSync()) {
            return;
        }
        foreach ([MappingManualFactory::CONTACT_OBJECT, MappingManualFactory::COMPANY_OBJECT] as $objectName) {
            $deletedOnIntegrationIds = $this->objectMappingRepository->getIntegrationIdsToDelete($objectName);

            if (!empty($deletedOnIntegrationIds)) {
                $isDeleted = $this->client->deleteBatch($objectName, array_column($deletedOnIntegrationIds, 'integration_object_id'));
                if ($isDeleted) {
                    $this->objectMappingRepository->deleteObjectMappingForObject(array_column($deletedOnIntegrationIds, 'internal_object_id'), $objectName);
                }

                if (defined('IN_MAUTIC_CONSOLE')) {
                    $consoleOutput = new ConsoleOutput();
                    $consoleOutput->writeln($this->translator->trans('pipedrive.pipedrive.deleted', ['%count%' => count($deletedOnIntegrationIds), '%object%' => $objectName]));
                }
            }
        }
    }
}
