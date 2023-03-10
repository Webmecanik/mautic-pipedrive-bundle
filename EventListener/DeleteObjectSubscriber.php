<?php

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Entity\FieldChangeRepository;
use Mautic\IntegrationsBundle\Event\SyncEvent;
use Mautic\IntegrationsBundle\IntegrationEvents;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\PipedriveBundle\Connection\Client;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
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

            $deleteOnInternalIds = $this->objectMappingRepository->getInternalIdsToDelete($objectName,
                $this->getDeletedIntegrationIds($objectName)
            );
            if (!empty($deleteOnInternalIds)) {
                $this->leadModel->deleteEntities(array_column($deleteOnInternalIds, 'internal_object_id'));
                $this->objectMappingRepository->deleteObjectMappingForObject(array_column($deleteOnInternalIds, 'internal_object_id'), $objectName);
                if (defined('IN_MAUTIC_CONSOLE')) {
                    $consoleOutput = new ConsoleOutput();
                    $consoleOutput->writeln($this->translator->trans('pipedrive.deleted', ['%count%' => count($deleteOnInternalIds)]));
                }
            }
        }
    }

    /**
     * @param $objectName
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \MauticPlugin\PipedriveBundle\Exception\PipedriveBundleMappingException
     * @throws \Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException
     * @throws \Mautic\IntegrationsBundle\Exception\InvalidCredentialsException
     * @throws \Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException
     */
    protected function getDeletedIntegrationIds($objectName): ?array
    {
        $nextStart      = 0;
        $integrationIds = [];
        while (true) {
            $page     = $nextStart ? ($nextStart / 500) + 1 : 1;
            $response = $this->client->getForPage($objectName, 500, $page);

            if ($response->hasError()) {
                $this->logger->error(
                    sprintf(
                        '%s: Error fetching %s data: %s',
                        Pipedrive2Integration::DISPLAY_NAME,
                        $objectName,
                        $response->getError()
                    )
                );

                return null;
            }

            foreach ($response->getData() as $datum) {
                $integrationIds[$datum['id']] = $datum['id'];
            }
            if (!$nextStart = $response->getNextStart()) {
                break;
            }
        }

        return $integrationIds;
    }
}
