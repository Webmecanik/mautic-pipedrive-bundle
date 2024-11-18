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
    public function __construct(private Client $client, private ObjectMappingRepository $objectMappingRepository, FieldChangeRepository $fieldChangeRepository, LeadModel $leadModel, private Config $config, private TranslatorInterface $translator)
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
