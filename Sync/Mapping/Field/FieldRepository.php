<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\Mapping\Field;

use Mautic\CoreBundle\Helper\CacheStorageHelper;
use MauticPlugin\PipedriveBundle\Connection\Client;
use MauticPlugin\PipedriveBundle\Enum\SettingsEnum;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;

class FieldRepository
{
    private \Mautic\CoreBundle\Helper\CacheStorageHelper $cacheProvider;

    private Client $client;

    private array $apiFields = [];

    public function __construct(CacheStorageHelper $cacheProvider, Client $client)
    {
        $this->cacheProvider = $cacheProvider;
        $this->client        = $client;
    }

    /**
     * Used by the sync engine so that it does not have to fetch the fields live with each object sync.
     *
     * @return Field[]
     */
    public function getFields(string $objectName): array
    {
        $cacheKey = $this->getCacheKey($objectName);
        $fields   = $this->cacheProvider->get($cacheKey);

        if (!$fields) {
            // Fields are empty or not found so refresh from the API
            $fields = $this->getFieldsFromApi($objectName);
        }

        return $this->hydrateFieldObjects($fields, $objectName);
    }

    /**
     * @return MappedFieldInfo[]
     */
    public function getRequiredFieldsForMapping(string $objectName): array
    {
        $fields       = $this->getFieldsFromApi($objectName);
        $fieldObjects = $this->hydrateFieldObjects($fields, $objectName);

        $requiredFields = [];
        foreach ($fieldObjects as $field) {
            if (!$field->isRequired()) {
                continue;
            }

            // Fields must have the name as the key
            $requiredFields[$field->getKey()] = new MappedFieldInfo($field);
        }

        return $requiredFields;
    }

    /**
     * @return MappedFieldInfo[]
     */
    public function getOptionalFieldsForMapping(string $objectName): array
    {
        $fields       = $this->getFieldsFromApi($objectName);
        $fieldObjects = $this->hydrateFieldObjects($fields, $objectName);

        $optionalFields = [];
        foreach ($fieldObjects as $field) {
            if ($field->isRequired()) {
                continue;
            }

            // Fields must have the name as the key
            $optionalFields[$field->getKey()] = new MappedFieldInfo($field);
        }

        return $optionalFields;
    }

    /**
     * Used by the config form to fetch the fields fresh from the API.
     */
    private function getFieldsFromApi(string $objectName): array
    {
        if (isset($this->apiFields[$objectName])) {
            return $this->apiFields[$objectName];
        }

        $fields = $this->client->getFields($objectName);

        // Refresh the cache with the fields just fetched
        $cacheKey = $this->getCacheKey($objectName);
        $this->cacheProvider->set($cacheKey, $fields);

        $this->apiFields[$objectName] = $fields;

        return $this->apiFields[$objectName];
    }

    private function getCacheKey(string $objectName): string
    {
        return sprintf('pipedrive.fields.%s', $objectName);
    }

    /**
     * @return Field[]
     */
    private function hydrateFieldObjects(array $fields, string $objectName): array
    {
        $fieldObjects = [];
        foreach ($fields as $field) {
            if ($this->isFieldEligibleForObject($field, $objectName)) {
                $fieldObjects[$field['key']] = new Field($field, $objectName);
            }
        }

        return $fieldObjects;
    }

    private function isFieldEligibleForObject(array $field, string $objectName): bool
    {
        if (MappingManualFactory::CONTACT_OBJECT === $objectName) {
            if (in_array($field['key'], ['name', 'id', 'org_id', 'owner_id', 'picture_id'])) {
                return false;
            }

            return true;
        }

        if (MappingManualFactory::COMPANY_OBJECT === $objectName) {
            if (in_array($field['key'], ['id', SettingsEnum::PIPEDRIVE_RELATION_PICTURE_FIELD_KEY])) {
                return false;
            }

            return true;
        }

        return false;
    }
}
