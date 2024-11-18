<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\DataExchange;

use GuzzleHttp\Exception\RequestException;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\FieldDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\ObjectChangeDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\OrderDAO;
use Mautic\IntegrationsBundle\Sync\Logger\DebugLogger;
use MauticPlugin\PipedriveBundle\Connection\Client;
use MauticPlugin\PipedriveBundle\Connection\DTO\Response;
use MauticPlugin\PipedriveBundle\Connection\Owners;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Field\FieldRepository;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;
use MauticPlugin\PipedriveBundle\Sync\Sync\CompanyRelation\CompanyRelationSyncToIntegration;
use Monolog\Logger;
use Psr\Log\LogLevel;

class OrderExecutioner
{
    const FORCE_SYNC = 'forceSync';

    private Client $client;

    private Config $config;

    private FieldRepository $fieldRepository;

    private Logger $logger;

    private Owners $owners;

    private ValueNormalizer $valueNormalizer;

    private ?OrderDAO $order = null;

    private \MauticPlugin\PipedriveBundle\Sync\Sync\CompanyRelation\CompanyRelationSyncToIntegration $relationSyncToIntegration;

    public function __construct(Client $client, CompanyRelationSyncToIntegration $relationSyncToIntegration, Logger $logger, FieldRepository $fieldRepository, Config $config, Owners $owners)
    {
        $this->client                    = $client;
        $this->valueNormalizer           = new ValueNormalizer();
        $this->relationSyncToIntegration = $relationSyncToIntegration;
        $this->logger                    = $logger;
        $this->fieldRepository           = $fieldRepository;
        $this->config                    = $config;
        $this->owners                    = $owners;
    }

    public function execute(OrderDAO $orderDAO): void
    {
        // This integration supports two objects, citizen and world3
        $forceSync = $orderDAO->getOptions()[self::FORCE_SYNC] ?? null;

        if ($this->config->disablePush() && true !== $forceSync) {
            return;
        }

        if (!$this->order) {
            $this->order = $orderDAO;
        }

        foreach ([MappingManualFactory::CONTACT_OBJECT, MappingManualFactory::COMPANY_OBJECT] as $objectName) {
            if (!$this->config->isEnabledSync($objectName) && true !== $forceSync) {
                continue;
            }
            if (true !== $forceSync) {
                sleep(1);
            }
            // Fetch the list of Mautic objects that have already been mapped to an object in the integration
            // and thus needs to be updated in the integration
            $identifiedObjects = $orderDAO->getIdentifiedObjects()[$objectName] ?? [];
            $this->updateObjects($identifiedObjects);

            // Fetch the list of Mautic objects that have not been mapped to an object in the integration and
            // thus may need to be created or modified in the integration.
            $unidentifiedObjects = $orderDAO->getUnidentifiedObjects()[$objectName] ?? [];
            $this->insertObjects($unidentifiedObjects);
        }
    }

    /**
     * @param ObjectChangeDAO[] $objects
     */
    private function updateObjects(array $objects)
    {
        foreach ($objects as $objectChangeDAO) {
            $objectName = $objectChangeDAO->getObject();
            $objectId   = $objectChangeDAO->getObjectId();
            try {
                $data     = $this->prepareFieldPayload($objectChangeDAO);
                $response = new Response(
                    $this->client->update(
                        $objectName,
                        $data,
                        $objectId
                    )
                );
                $code  = $response->getCode();
                $error = $response->getError();
            } catch (RequestException $exception) {
                $code  = $exception->getCode();
                $error = $exception->getResponse()->getBody()->getContents();
            }

            $this->updateObjectsStatus($objectChangeDAO, $code, $error);
        }
    }

    /**
     * @param ObjectChangeDAO[] $objects
     */
    private function insertObjects(array $objects)
    {
        foreach ($objects as $objectChangeDAO) {
            $objectName = $objectChangeDAO->getObject();
            $data       = $this->prepareFieldPayload($objectChangeDAO);

            // need required fields
            if (!$this->hasRequiredFields($objectName, $data)) {
                DebugLogger::log(
                    Pipedrive2Integration::DISPLAY_NAME,
                    sprintf(
                        'Stop sync to integration, required fields missing for object %s and  ID  %s',
                        $objectName,
                        $item['id'] ?? 'unknown'
                    ),
                    __CLASS__.':'.__FUNCTION__,
                    [],
                    LogLevel::ERROR
                );
                continue;
            }

            try {
                $searchResponse = new Response($this->client->search($objectName, $data));
                $found          = $searchResponse->getData();
            } catch (RequestException $e) {
                DebugLogger::log(
                    Pipedrive2Integration::DISPLAY_NAME,
                    sprintf('Search for object %s with error: %s', $objectName, $e->getMessage()),
                    __CLASS__.':'.__FUNCTION__,
                    [],
                    LogLevel::ERROR
                );
                $this->logger->error(Pipedrive2Integration::DISPLAY_NAME.':'.$e->getMessage());
            }

            if (!empty($found['items'])) {
                $objectId = $found['items'][0]['item']['id'] ?? null;
                $response = new Response($this->client->update(
                    $objectName,
                    $data,
                    $objectId
                ));
            } else {
                try {
                    // create contact
                    $response = new Response($this->client->create(
                        $objectName,
                        $data
                    ));
                    $objectId = $response->getData()['id'] ?? null;
                } catch (RequestException $exception) {
                    $contents = $exception->getResponse()->getBody()->getContents();
                    $this->logger->error(json_encode($data));
                    $this->logger->error($contents);
                    continue;
                    // throw $exception;
                }
            }

            if ($response->hasError()) {
                $this->logger->error($response->getError());
            }
            // add object mapping If requests success
            if ($objectId && 200 === $response->getCode()) {
                $this->order->addObjectMapping(
                    $objectChangeDAO,
                    $objectChangeDAO->getObject(),
                    $objectId
                );
            }
        }
    }

    private function prepareFieldPayload(ObjectChangeDAO $objectChangeDAO): array
    {
        $fields              = $objectChangeDAO->getFields();
        $datum               = [];
        $objectName          = $objectChangeDAO->getObject();
        $fieldsForObject     = $this->fieldRepository->getFields($objectName);
        /** @var FieldDAO $field */
        foreach ($fields as $field) {
            if ('owner_id' === $field->getName()) {
                $datum[$field->getName()] = $this->owners->getPipedriveId((int) $field->getValue()->getNormalizedValue());
            } else {
                // Transform the data format from Mautic to what the integration expects
                $datum[$field->getName()] = $this->valueNormalizer->normalizeForIntegration(
                    $field->getValue(),
                    $fieldsForObject[$field->getName()] ?? null
                );
            }
        }

        if (MappingManualFactory::CONTACT_OBJECT === $objectName) {
            $datum['name']   = sprintf('%s %s', $datum['first_name'] ?? '', $datum['last_name'] ?? '');
            if ($this->config->shouldSyncContactsCompanyToIntegration()) {
                $datum['org_id'] = $this->relationSyncToIntegration->sync(
                $objectChangeDAO,
                MappingManualFactory::COMPANY_OBJECT,
                $this->order,
                [self::FORCE_SYNC => true]
            );
            }
        }

        return $datum;
    }

    protected function updateObjectsStatus(ObjectChangeDAO $objectChangeDAO, int $code = 200, string $error = null): void
    {
        switch ($code) {
            case 200:
                $this->order->updateLastSyncDate($objectChangeDAO);
                break;
            case 404:
                $this->order->updateLastSyncDate($objectChangeDAO);
                $this->order->deleteObject($objectChangeDAO);
                break;
            case 503:
                $this->order->retrySyncLater($objectChangeDAO);
                break;
            case 400:
            case 403:
            default:
                $this->order->noteObjectSyncIssue($objectChangeDAO, $error);
                $this->logger->error($error);
        }
    }

    private function hasRequiredFields(string $objectName, array $data): bool
    {
        $requiredFields = $this->fieldRepository->getRequiredFieldsForMapping($objectName);
        $mappedFields   = $this->config->getMappedFields($objectName);
        foreach ($mappedFields as $integrationField => $mauticField) {
            if (isset($requiredFields[$integrationField])) {
                if (empty($data[$integrationField])) {
                    return false;
                }
            }

            if (MappingManualFactory::CONTACT_OBJECT === $objectName) {
                if (empty($data['first_name']) && empty($data['last_name'])) {
                    return false;
                }
            }
        }

        return true;
    }
}
