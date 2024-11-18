<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\DataExchange;

use Mautic\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;
use Mautic\IntegrationsBundle\Sync\ValueNormalizer\ValueNormalizerInterface;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Field\Field;

class ValueNormalizer implements ValueNormalizerInterface
{
    // Example of a type that could require values to be transformed to supported format by each side of the sync
    public const BOOLEAN_TYPE = 'bool';

    public const DATE         = 'date';

    public const DOUBLE       = 'double';

    public const ENUM         = 'enum';

    public const ORG          = 'org';

    public const PHONE        = 'phone';

    public const SET          = 'set';

    public function normalizeForIntegration(NormalizedValueDAO $value, Field $field = null)
    {
        return match ($value->getType()) {
            NormalizedValueDAO::TIME_TYPE     => $value->getNormalizedValue() instanceof \DateTimeInterface ? $value->getNormalizedValue()->format('H:i:s') : $value->getNormalizedValue(),
            NormalizedValueDAO::DATE_TYPE     => $value->getNormalizedValue() instanceof \DateTimeInterface ? $value->getNormalizedValue()->format('Y-m-d') : $value->getNormalizedValue(),
            NormalizedValueDAO::DATETIME_TYPE => $value->getNormalizedValue() instanceof \DateTimeInterface ? $value->getNormalizedValue()->format('Y-m-d H:i:s') : $value->getNormalizedValue(),
            NormalizedValueDAO::SELECT_TYPE, NormalizedValueDAO::MULTISELECT_TYPE => $this->getOptionsForPipedrive($value->getNormalizedValue(), $field),
            default => $value->getNormalizedValue(),
        };
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

        $values  = is_array($value) ? $value : explode(',', (string) $value);
        $options = [];

        foreach ($values as $val) {
            $val = is_array($val) ? ($val['value'] ?? null) : $val;
            if (null !== $val) {
                foreach ($field->getOptions() as $option) {
                    if ($option['id'] == $val) {
                        $options[] = $option['label'];
                        break;
                    }
                }
            }
        }

        return !empty($options) ? implode('|', $options) : null;
    }

    protected function getOptionsForPipedrive($value, ?Field $field): string|array|null
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

        if ('label_ids' === $field->getKey()) {
            return $options;
        }

        return implode(',', $options);
    }
}
