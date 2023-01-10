<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Form\Type;

use Mautic\IntegrationsBundle\Form\Type\ActivityListType;
use MauticPlugin\PipedriveBundle\Integration\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigFeaturesType extends AbstractType
{
    const ACTIVITY_EVENTS = 'activityEvents';

    const DELETE          = 'delete';

    const OWNER           = 'owner';

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            self::ACTIVITY_EVENTS,
            ActivityListType::class
        );
    }
}
