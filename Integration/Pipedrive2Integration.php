<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class Pipedrive2Integration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const NAME         = 'Pipedrive2';
    public const DISPLAY_NAME = 'Pipedrive 2';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/PipedriveBundle/Assets/img/pipedrive.png';
    }
}
