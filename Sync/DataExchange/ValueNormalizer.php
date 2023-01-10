<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\DataExchange;

use Mautic\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;
use Mautic\IntegrationsBundle\Sync\ValueNormalizer\ValueNormalizerInterface;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Field\Field;

class ValueNormalizer implements ValueNormalizerInterface
{
    // Example of a type that could require values to be transformed to supported format by each side of the sync
    const BOOLEAN_TYPE = 'bool';

    const DATE         = 'date';

    const DOUBLE       = 'double';

    const ENUM         = 'enum';

    const ORG          = 'org';

    const PHONE        = 'phone';

    const SET          = 'set';

    public function normalizeForIntegration(NormalizedValueDAO $value, Field $field = null)
    {
        switch ($value->getType()) {
            case NormalizedValueDAO::TIME_TYPE:
                return $value->getNormalizedValue() instanceof \DateTimeInterface ? $value->getNormalizedValue()->format('H:i:s') : $value->getNormalizedValue();
            case NormalizedValueDAO::DATE_TYPE:
                return $value->getNormalizedValue() instanceof \DateTimeInterface ? $value->getNormalizedValue()->format('Y-m-d') : $value->getNormalizedValue();
            case NormalizedValueDAO::DATETIME_TYPE:
                return $value->getNormalizedValue() instanceof \DateTimeInterface ? $value->getNormalizedValue()->format('Y-m-d H:i:s') : $value->getNormalizedValue();
            case NormalizedValueDAO::SELECT_TYPE:
            case NormalizedValueDAO::MULTISELECT_TYPE:
              return $this->getOptionsForPipedrive($value->getNormalizedValue(), $field);
            default:
                return $value->getNormalizedValue();
        }
    }

    public function normalizeForMautic($value, $type, Field $field = null): NormalizedValueDAO
    {
        switch ($type) {
            case self::ENUM:
                return new NormalizedValueDAO(NormalizedValueDAO::SELECT_TYPE, $value, $this->getOptionsForMautic($value, $field));
            case self::SET:
                return new NormalizedValueDAO(NormalizedValueDAO::MULTISELECT_TYPE, $value, $this->getOptionsForMautic($value, $field));
            case self::DOUBLE:
                return new NormalizedValueDAO(NormalizedValueDAO::FLOAT_TYPE, $value, (float) $value);
            case self::ORG:
                return new NormalizedValueDAO(NormalizedValueDAO::REFERENCE_TYPE, $value, $value);
            case self::DATE:
                return new NormalizedValueDAO(NormalizedValueDAO::DATE_TYPE, $value, $value);
            case self::BOOLEAN_TYPE:
                // Mautic requires 1 or 0 for booleans
                return new NormalizedValueDAO(NormalizedValueDAO::BOOLEAN_TYPE, $value, (int) $value);
            default:
                if (is_array($value)) {
                    $value = $value[0]['value'] ?? $value['value'] ?? null;
                }

                return new NormalizedValueDAO(NormalizedValueDAO::TEXT_TYPE, $value, (string) $value);
        }
    }

    /**
     * Replace IDs to labels - format what we use in Mautic.
     *
     * @param string|int|null $value
     */
    protected function getOptionsForMautic($value, ?Field $field): ?string
    {
        if (!$value) {
            return null;
        }
        $values  = explode(',', (string) $value);
        $options = [];
        foreach ($values as $val) {
            foreach ($field->getOptions() as $option) {
                if ($option['id'] == $val) {
                    $options[] = $option['label'];
                }
            }
        }

        return implode('|', $options);
    }

    protected function getOptionsForPipedrive($value, ?Field $field): ?string
    {
        if (!$value) {
            return null;
        }
        $values  = explode('|', (string) $value);
        $options = [];
        foreach ($values as $val) {
            foreach ($field->getOptions() as $option) {
                if ($option['label'] == $val) {
                    $options[] = $option['id'];
                }
            }
        }

        return implode(',', $options);
    }
}
