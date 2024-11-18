<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Form\Type;

use Mautic\IntegrationsBundle\Form\Type\ActivityListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigFeaturesType extends AbstractType
{
    public const ACTIVITY_EVENTS = 'activityEvents';

    public const DELETE          = 'delete';

    public const OWNER           = 'owner';

    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            self::ACTIVITY_EVENTS,
            ActivityListType::class
        );
    }
}
