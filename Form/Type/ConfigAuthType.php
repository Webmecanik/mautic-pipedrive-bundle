<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Form\Type;

use MauticPlugin\PipedriveBundle\Enum\SettingsEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ConfigAuthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $clientSecret   = null;
        $configProvider = $options['integration'];
        if ($configProvider->getIntegrationConfiguration() && $configProvider->getIntegrationConfiguration()->getApiKeys()) {
            $data         = $configProvider->getIntegrationConfiguration()->getApiKeys();
            $clientSecret = $data['client_secret'] ?? null;
        }

        $builder->add(
            SettingsEnum::PIPEDRIVE_INSTANCE_NAME_FIELD,
            TextType::class,
            [
                'label'      => 'pipedrive.instance_name',
                'label_attr' => ['class' => 'control-label'],
                'required'   => true,
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'pipedrive.instance_name.desc',
                ],
                'constraints'       => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
            ]
        );

        $builder->add(
            'client_id',
            TextType::class,
            [
                'label'      => 'pipedrive.client_id',
                'label_attr' => ['class' => 'control-label'],
                'required'   => true,
                'attr'       => [
                    'class' => 'form-control',
                ],
                'constraints'       => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
            ]
        );

        $builder->add(
            'client_secret',
            PasswordType::class,
            [
                'label'      => 'pipedrive.client_secret',
                'label_attr' => ['class' => 'control-label'],
                'required'   => true,
                'attr'       => [
                    'class' => 'form-control',
                ],
                'empty_data'        => $clientSecret,
                'constraints'       => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
            ]
        );
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults(
            [
                'integration' => null,
            ]
        );
    }
}
