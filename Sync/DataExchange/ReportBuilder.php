<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\DataExchange;

use GuzzleHttp\Exception\GuzzleException;
use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Exception\InvalidCredentialsException;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\InputOptionsDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Report\FieldDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Report\ObjectDAO as ReportObjectDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Report\ReportDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Request\ObjectDAO as RequestObjectDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;
use Mautic\IntegrationsBundle\Sync\Exception\ObjectNotFoundException;
use Mautic\IntegrationsBundle\Sync\Logger\DebugLogger;
use MauticPlugin\PipedriveBundle\Connection\Client;
use MauticPlugin\PipedriveBundle\Connection\Owners;
use MauticPlugin\PipedriveBundle\Enum\SettingsEnum;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Field\Field;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Field\FieldRepository;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;
use Psr\Log\LogLevel;

class ReportBuilder
{
    private Client $client;

    private Config $config;

    private FieldRepository $fieldRepository;

    private Owners $owners;

    private ValueNormalizer $valueNormalizer;

    private ?ReportDAO $report = null;

    public function __construct(Client $client, Config $config, FieldRepository $fieldRepository, Owners $owners)
    {
        $this->client          = $client;
        $this->config          = $config;
        $this->fieldRepository = $fieldRepository;

        // Value normalizer transforms value types expected by each side of the sync
        $this->valueNormalizer = new ValueNormalizer();
        $this->owners          = $owners;
    }

    /**
     * @param RequestObjectDAO[] $requestedObjects
     *
     * @throws GuzzleException
     * @throws IntegrationNotFoundException
     * @throws InvalidCredentialsException
     * @throws PluginNotConfiguredException
     */
    public function build(int $page, array $requestedObjects, InputOptionsDAO $options): ReportDAO
    {
        // Set the options this integration supports (see InputOptionsDAO for others)
        $startDateTime = $options->getStartDateTime();
        $endDateTime   = $options->getEndDateTime();

        $this->report = new ReportDAO(Pipedrive2Integration::NAME);

        if ($this->config->disablePull()) {
            return $this->report;
        }

        // do not sync If there is no force Ids or date range or first time sync
        if (!$options->getIntegrationObjectIds() && !$startDateTime && !$options->isFirstTimeSync()) {
            return $this->report;
        }

        foreach ($requestedObjects as $requestedObject) {
            $objectName = $requestedObject->getObject();

            if (!$this->config->isEnabledSync($objectName)) {
                continue;
            }

            if ($objectIdsDAO = $options->getIntegrationObjectIds()) {
                $itemsToMautic = $this->getItemsToMauticFromObjectIds($objectIdsDAO, $objectName);
            } else {
                $itemsToMautic = $this->client->get(
                    $objectName,
                    $startDateTime,
                    $endDateTime,
                    $page
                );
            }
            // Add the modified items to the report
            $this->addModifiedItems($objectName, is_object($itemsToMautic) ? [$itemsToMautic] : $itemsToMautic);
        }

        return $this->report;
    }

    private function addModifiedItems(string $objectName, array $changeList): void
    {
        // Get the field list to know what the field types are
        $fields       = $this->fieldRepository->getFields($objectName);
        $mappedFields = $this->config->getMappedFields($objectName);

        foreach ($changeList as $item) {
            if (!$this->hasRequiredFields($objectName, $item)) {
                DebugLogger::log(
                    Pipedrive2Integration::DISPLAY_NAME,
                    sprintf(
                        'Stop sync from integration, required fields missing for object %s and  ID  %s',
                        $objectName,
                        $item['id'] ?? 'unknown'
                    ),
                    __CLASS__.':'.__FUNCTION__,
                    [],
                    LogLevel::ERROR
                );
                continue;
            }

            $objectDAO = new ReportObjectDAO(
                $objectName,
                // Set the ID from the integration
                $item['id'],
                // Set the date/time when the full object was last modified or created
                new \DateTime($item[SettingsEnum::UPDATE_TIME] ?? $item[SettingsEnum::ADD_TIME])
            );
            foreach ($item as $fieldAlias => $fieldValue) {
                if (!isset($fields[$fieldAlias]) || !isset($mappedFields[$fieldAlias])) {
                    // Field is not recognized, or it's not mapped so ignore
                    continue;
                }

                /** @var Field $field */
                $field = $fields[$fieldAlias];

                // The sync is currently from Integration to Mautic so normalize the values for storage in Mautic
                $normalizedValue = $this->valueNormalizer->normalizeForMautic(
                    $fieldValue,
                    $field->getDataType(),
                    $field
                );

                // If the integration supports field level tracking with timestamps, update FieldDAO::setChangeDateTime as well
                $objectDAO->addField(new FieldDAO($fieldAlias, $normalizedValue));
            }

            if (MappingManualFactory::CONTACT_OBJECT === $objectName && $this->config->shouldSyncContactsCompanyFromIntegration()) {
                $value           = $item['org_id']['name'] ?? null;
                $normalizedValue = new NormalizedValueDAO(NormalizedValueDAO::STRING_TYPE, $value, $value);
                $objectDAO->addField(new FieldDAO('org_id', $normalizedValue));
            }

            if ($this->config->shouldSyncOwner()) {
                $value            = $item['owner_id']['email'] ?? null;
                $internalId       = $this->owners->getInternalIdFromEmail($value);
                if ($internalId) {
                    $normalizedValue  = new NormalizedValueDAO(NormalizedValueDAO::INT_TYPE, $value, $internalId);
                    $objectDAO->addField(new FieldDAO('owner_id', $normalizedValue));
                }
            }

            // Add the modified/new item to the report
            $this->report->addObject($objectDAO);
        }
    }

    protected function getItemsToMauticFromObjectIds(
        \Mautic\IntegrationsBundle\Sync\DAO\Sync\ObjectIdsDAO $objectIdsDAO,
        string $objectName
    ): array {
        $itemsToMautic = [];
        try {
            $objectIds = $objectIdsDAO->getObjectIdsFor($objectName);
            foreach ($objectIds as $objectId) {
                if ($data = $this->client->find($objectName, $objectId)) {
                    $itemsToMautic[] = $data;
                }
            }
        } catch (ObjectNotFoundException $objectNotFoundException) {
        }

        return $itemsToMautic;
    }

    private function hasRequiredFields(string $objectName, array $data)
    {
        $requiredFields = $this->fieldRepository->getRequiredFieldsForMapping($objectName);
        $mappedFields   = $this->config->getMappedFields($objectName);
        foreach ($mappedFields as $integrationField => $mauticField) {
            if (isset($requiredFields[$integrationField])) {
                $value = $data[$integrationField];
                if (is_array($value)) {
                    $value = $value[0]['value'] ?? ($value['value'] ?? null);
                }
                if (empty($value)) {
                    return false;
                }
            }
        }

        return true;
    }
}
