<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Integration\Support;

use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormAuthInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormAuthorizeButtonInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormCallbackInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeatureSettingsInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeaturesInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormSyncInterface;
use Mautic\IntegrationsBundle\Mapping\MappedFieldInfoInterface;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Company;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Contact;
use MauticPlugin\PipedriveBundle\Enum\SettingsEnum;
use MauticPlugin\PipedriveBundle\Form\Type\ConfigAuthType;
use MauticPlugin\PipedriveBundle\Form\Type\ConfigFeaturesType;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Field\FieldRepository;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Translation\TranslatorInterface;

class ConfigSupport extends Pipedrive2Integration implements ConfigFormInterface, ConfigFormAuthInterface, ConfigFormFeatureSettingsInterface, ConfigFormSyncInterface, ConfigFormFeaturesInterface, ConfigFormAuthorizeButtonInterface, ConfigFormCallbackInterface
{
    use DefaultConfigFormTrait;

    const DISABLE_PUSH                           = 'disable_push';
    const DISABLE_PULL                           = 'disable_pull';

    const SYNC_CONTACTS_COMPANY_FROM_INTEGRATION = 'contacts_company_from_integration';

    const SYNC_CONTACTS_COMPANY_TO_INTEGRATION   = 'contacts_company_to_integration';

    private FieldRepository $fieldRepository;

    private Config $config;

    private Session $session;

    private Router $router;

    private TranslatorInterface $translator;

    public function __construct(FieldRepository $fieldRepository, Config $config, Session $session, Router $router, TranslatorInterface $translator)
    {
        $this->fieldRepository = $fieldRepository;
        $this->session         = $session;
        $this->router          = $router;
        $this->config          = $config;
        $this->translator      = $translator;
    }

    public function getAuthConfigFormName(): string
    {
        return ConfigAuthType::class;
    }

    public function getFeatureSettingsConfigFormName(): string
    {
        return ConfigFeaturesType::class;
    }

    public function getSyncConfigObjects(): array
    {
        return [
            MappingManualFactory::CONTACT_OBJECT => 'pipedrive.object.contact',
            MappingManualFactory::COMPANY_OBJECT => 'pipedrive.object.company',
        ];
    }

    public function getSyncMappedObjects(): array
    {
        return [
            MappingManualFactory::CONTACT_OBJECT => Contact::NAME,
            MappingManualFactory::COMPANY_OBJECT => Company::NAME,
        ];
    }

    /**
     * @return MappedFieldInfoInterface[]
     */
    public function getRequiredFieldsForMapping(string $objectName): array
    {
        return $this->fieldRepository->getRequiredFieldsForMapping($objectName);
    }

    /**
     * @return MappedFieldInfoInterface[]
     */
    public function getOptionalFieldsForMapping(string $objectName): array
    {
        return $this->fieldRepository->getOptionalFieldsForMapping($objectName);
    }

    /**
     * @return MappedFieldInfoInterface[]
     */
    public function getAllFieldsForMapping(string $objectName): array
    {
        // Order fields by required alphabetical then optional alphabetical
        $sorter = fn (MappedFieldInfoInterface $field1, MappedFieldInfoInterface $field2) => strnatcasecmp($field1->getLabel(), $field2->getLabel());

        $requiredFields = $this->fieldRepository->getRequiredFieldsForMapping($objectName);
        uasort($requiredFields, $sorter);

        $optionalFields = $this->fieldRepository->getOptionalFieldsForMapping($objectName);
        uasort($optionalFields, $sorter);

        return array_merge(
            $requiredFields,
            $optionalFields
        );
    }

    public function getSupportedFeatures(): array
    {
        return [
            ConfigFormFeaturesInterface::FEATURE_SYNC          => 'mautic.integration.feature.sync',
            ConfigFormFeaturesInterface::FEATURE_PUSH_ACTIVITY => 'mautic.integration.feature.push_activity',
            self::DISABLE_PUSH                                 => 'pipedrive.disable.push',
            self::DISABLE_PULL                                 => 'pipedrive.disable.pull',
            ConfigFeaturesType::OWNER                          => 'pipedrive.owner.sync',
            ConfigFeaturesType::DELETE                         => 'pipedrive.delete.sync',
            self::SYNC_CONTACTS_COMPANY_TO_INTEGRATION         => 'pipedrive.sync.contacts_company_to_integration',
            self::SYNC_CONTACTS_COMPANY_FROM_INTEGRATION       => 'pipedrive.sync.contacts_company_from_integration',
        ];
    }

    public function isAuthorized(): bool
    {
        return $this->config->isAuthorized();
    }

    public function getAuthorizationUrl(): string
    {
        // Generate and set the state in the session so that it can be validated when the authorization process redirects to the redirect URL
        $state = EncryptionHelper::generateKey();
        $this->session->set('pipedrive.state', $state);

        $params = [
            'client_id'     => $this->getIntegrationConfiguration()->getApiKeys()['client_id'] ?? '',
            'client_secret' => $this->getIntegrationConfiguration()->getApiKeys()['client_secret'] ?? '',
            'response_type' => 'code',
            'redirect_uri'  => $this->getRedirectUri(),
            'scope'         => 'api refresh_token',
            'state'         => $state,
        ];

        return $this->createQuery(SettingsEnum::PIPEDRIVE_AUTH_URL, $params);
    }

    public function getCallbackHelpMessageTranslationKey(): string
    {
        if ($this->isAuthorized()) {
            return $this->translator->trans('pipedrive.auth.is_authorized', ['%access_token%' => $this->config->getApiKeys()['access_token']]);
        }

        return $this->translator->trans('pipedrive.auth.is_not_authorized');
    }

    public function getRedirectUri(): string
    {
        return $this->router->generate(
            'mautic_integration_public_callback',
            ['integration' => Pipedrive2Integration::NAME],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function createQuery(string $uri, $params = []): string
    {
        if (0 === count($params)) {
            return $uri;
        }

        foreach ($params as $param => $value) {
            if (false !== strpos($uri, '?')) {
                $uri = $uri.'&'.$param.'='.$value;
            } else {
                $uri = $uri.'?'.$param.'='.$value;
            }
        }

        return $uri;
    }
}
