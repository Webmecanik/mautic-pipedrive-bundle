<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\Mapping\Field;

use Mautic\IntegrationsBundle\Sync\DAO\Mapping\ObjectMappingDAO;
use MauticPlugin\PipedriveBundle\Enum\SettingsEnum;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;

class Field
{
    private $name;
    private $key;
    private $label;
    private $dataType;
    private ?bool $isRequired = null;
    private bool $isWritable;

    private array $options;

    public function __construct(array $field = [], string $objectName)
    {
        $this->name       = $field['name'] ?? '';
        $this->key        = $field['key'] ?? '';
        $this->label      = $field['label'] ?? '';
        $this->dataType   = $field['field_type'] ?? 'text';
        $this->options    = (array) ($field['options'] ?? []);
        if (MappingManualFactory::CONTACT_OBJECT === $objectName) {
            $this->isRequired = in_array($this->key, SettingsEnum::PIPEDRIVE_PERSON_REQUIRED_FIELDS);
        } else {
            $this->isRequired = in_array($this->key, SettingsEnum::PIPEDRIVE_ORGANIZATION_REQUIRED_FIELDS);
        }
        $this->isWritable = isset($field['key']) && SettingsEnum::PIPEDRIVE_PERSON_PRIMARY_ID_KEY !== $field['key'];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getDataType(): string
    {
        return $this->dataType;
    }

    /**
     * @return array
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function isWritable(): bool
    {
        return $this->isWritable;
    }

    public function getSupportedSyncDirection(): string
    {
        return $this->isWritable ? ObjectMappingDAO::SYNC_BIDIRECTIONALLY : ObjectMappingDAO::SYNC_TO_MAUTIC;
    }
}
