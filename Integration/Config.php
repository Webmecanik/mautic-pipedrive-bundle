<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Exception\InvalidValueException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeaturesInterface;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\PipedriveBundle\Form\Type\ConfigFeaturesType;
use MauticPlugin\PipedriveBundle\Integration\Support\ConfigSupport;

class Config
{
    /**
     * @var IntegrationsHelper
     */
    protected $integrationsHelper;

    /**
     * @var array[]
     */
    protected $fieldDirections = [];

    /**
     * @var array[]
     */
    protected $mappedFields = [];

    public function __construct(IntegrationsHelper $integrationsHelper)
    {
        $this->integrationsHelper = $integrationsHelper;
    }

    public function isPublished(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();

            return (bool) $integration->getIsPublished() ?: false;
        } catch (IntegrationNotFoundException $e) {
            return false;
        }
    }

    public function isEnabledSync(string $objectName): bool
    {
        $hasEnabledObject = array_key_exists(
            $objectName,
            array_flip($this->getSettings()['sync']['objects'] ?? [])
        );

        return $this->isPublished() && $this->isConfigured() && $hasEnabledObject;
    }

    private function getSettings(): array
    {
        if (null === $this->getIntegrationEntity()) {
            return [];
        }

        return $this->getIntegrationEntity()->getFeatureSettings();
    }

    /**
     * @return mixed[]
     */
    public function getFeatureSettings(): array
    {
        try {
            $integration = $this->getIntegrationEntity();

            return $integration->getFeatureSettings() ?: [];
        } catch (IntegrationNotFoundException $e) {
            return [];
        }
    }

    /**
     * @return string[]
     */
    public function getApiKeys(): array
    {
        try {
            $integration = $this->getIntegrationEntity();

            return $integration->getApiKeys() ?: [];
        } catch (IntegrationNotFoundException $e) {
            return [];
        }
    }

    /**
     * @throws InvalidValueException
     */
    public function getFieldDirection(string $objectName, string $alias): string
    {
        if (isset($this->getMappedFieldsDirections($objectName)[$alias])) {
            return $this->getMappedFieldsDirections($objectName)[$alias];
        }

        throw new InvalidValueException("There is no field direction for '{$objectName}' field '${alias}'.");
    }

    /**
     * Returns mapped fields that the user configured for this integration in the format of [field_alias => mautic_field_alias].
     *
     * @return string[]
     */
    public function getMappedFields(string $objectName): array
    {
        if (isset($this->mappedFields[$objectName])) {
            return $this->mappedFields[$objectName];
        }

        $fieldMappings                   = $this->getFeatureSettings()['sync']['fieldMappings'][$objectName] ?? [];
        $this->mappedFields[$objectName] = [];
        foreach ($fieldMappings as $field => $fieldMapping) {
            $this->mappedFields[$objectName][$field] = $fieldMapping['mappedField'];
        }

        return $this->mappedFields[$objectName];
    }

    /**
     * Returns direction of what field to sync where in the format of [field_alias => direction].
     *
     * @return string[]
     */
    private function getMappedFieldsDirections(string $objectName): array
    {
        if (isset($this->fieldDirections[$objectName])) {
            return $this->fieldDirections[$objectName];
        }

        $fieldMappings = $this->getFeatureSettings()['sync']['fieldMappings'][$objectName] ?? [];

        $this->fieldDirections[$objectName] = [];
        foreach ($fieldMappings as $field => $fieldMapping) {
            $this->fieldDirections[$objectName][$field] = $fieldMapping['syncDirection'];
        }

        return $this->fieldDirections[$objectName];
    }

    public function isConfigured(): bool
    {
        $apiKeys = $this->getApiKeys();

        return !empty($apiKeys['client_id']) && !empty($apiKeys['client_secret']);
    }

    public function isAuthorized(): bool
    {
        $apiKeys = $this->getApiKeys();

        return !empty($apiKeys['refresh_token']);
    }

    public function shouldDelete(): bool
    {
        return $this->isPublished() && in_array(ConfigFeaturesType::DELETE, $this->getIntegrationEntity()->getSupportedFeatures());
    }

    public function shouldSyncOwner(): bool
    {
        return $this->isPublished() && in_array(ConfigFeaturesType::OWNER, $this->getIntegrationEntity()->getSupportedFeatures());
    }

    public function shouldSyncContactsCompanyFromIntegration(): bool
    {
        return $this->isPublished() && in_array(ConfigSupport::SYNC_CONTACTS_COMPANY_FROM_INTEGRATION, $this->getIntegrationEntity()->getSupportedFeatures());
    }

    public function shouldSyncContactsCompanyToIntegration(): bool
    {
        return $this->isPublished() && in_array(ConfigSupport::SYNC_CONTACTS_COMPANY_TO_INTEGRATION, $this->getIntegrationEntity()->getSupportedFeatures());
    }

    public function disablePush(): bool
    {
        return $this->isPublished() && in_array(ConfigSupport::DISABLE_PUSH, $this->getIntegrationEntity()->getSupportedFeatures());
    }

    public function disablePull(): bool
    {
        return $this->isPublished() && in_array(ConfigSupport::DISABLE_PULL, $this->getIntegrationEntity()->getSupportedFeatures());
    }

    public function shouldSyncActivities(): bool
    {
        return $this->isPublished()
            && in_array(ConfigFormFeaturesInterface::FEATURE_PUSH_ACTIVITY, $this->getIntegrationEntity()->getSupportedFeatures())
            && !empty($this->getActivities());
    }

    public function getActivities(): array
    {
        return $this->getFeatureSettings()['integration'][ConfigFeaturesType::ACTIVITY_EVENTS] ?? [];
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function getIntegrationEntity(): Integration
    {
        $integrationObject = $this->integrationsHelper->getIntegration(Pipedrive2Integration::NAME);

        return $integrationObject->getIntegrationConfiguration();
    }
}
