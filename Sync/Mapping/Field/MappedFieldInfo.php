<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\Mapping\Field;

use Mautic\IntegrationsBundle\Mapping\MappedFieldInfoInterface;

class MappedFieldInfo implements MappedFieldInfoInterface
{
    public function __construct(private Field $field)
    {
    }

    public function getName(): string
    {
        return $this->field->getKey();
    }

    public function getLabel(): string
    {
        return $this->field->getLabel();
    }

    public function showAsRequired(): bool
    {
        return $this->field->isRequired();
    }

    public function hasTooltip(): bool
    {
        return false;
    }

    public function getTooltip(): string
    {
        return '';
    }

    public function isBidirectionalSyncEnabled(): bool
    {
        return $this->field->isWritable();
    }

    public function isToIntegrationSyncEnabled(): bool
    {
        return $this->field->isWritable();
    }

    public function isToMauticSyncEnabled(): bool
    {
        return true;
    }
}
