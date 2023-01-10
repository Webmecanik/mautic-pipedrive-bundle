<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Connection;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\HttpFactory;
use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Exception\InvalidCredentialsException;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;
use MauticPlugin\PipedriveBundle\Connection\Config as ConnectionConfig;
use MauticPlugin\PipedriveBundle\Connection\DTO\Response;
use MauticPlugin\PipedriveBundle\Enum\SettingsEnum;
use MauticPlugin\PipedriveBundle\Exception\PipedriveBundleMappingException;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

class Client
{
    const LIMIT = 100;

    private string $apiUrl;

    private \Mautic\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\HttpFactory $httpFactory;

    private \MauticPlugin\PipedriveBundle\Integration\Config $config;

    private ConnectionConfig $connectionConfig;

    private \Monolog\Logger $logger;

    private \Symfony\Component\Routing\Router $router;

    public function __construct(HttpFactory $httpFactory, Config $config, ConnectionConfig $connectionConfig, Logger $logger, Router $router)
    {
        $this->httpFactory      = $httpFactory;
        $this->config           = $config;
        $this->connectionConfig = $connectionConfig;
        $this->logger           = $logger;
        $this->router           = $router;
        $this->apiUrl           = sprintf(
            'https://%s.pipedrive.com/v1/',
            $config->getApiKeys()[SettingsEnum::PIPEDRIVE_INSTANCE_NAME_FIELD] ?? ''
        );
    }

    public function find(string $objectName, string $objectId)
    {
        try {
            $response    = $this->getClient()->request(
                'GET',
                sprintf('%s/%s', $this->getUrl($objectName.'s'), $objectId)
            );
            $responseDTO = new Response($response);
            if (!$responseDTO->hasError()) {
                return $responseDTO->getData();
            }
            $error = $responseDTO->getError();
        } catch (RequestException $requestException) {
            $error = $requestException->getMessage();
        }
        $this->logError(__METHOD__, func_get_args(), $error);
    }

    /**
     * @throws GuzzleException
     * @throws PluginNotConfiguredException
     * @throws IntegrationNotFoundException
     * @throws InvalidCredentialsException
     */
    public function get(string $objectName, ?\DateTimeInterface $startDateTime, ?\DateTimeInterface $endDateTime, int $page = 1, int $limit = 500)
    {
        $response =  $this->getForPage($objectName, $limit, $page, $startDateTime, $endDateTime);

        return $this->getDataByDateRange($response, $startDateTime, $endDateTime);
    }

    public function create(string $objectName, array $payload): ResponseInterface
    {
        $url      = $this->getUrl(MappingManualFactory::CONTACT_OBJECT === $objectName ? SettingsEnum::PIPEDRIVE_PERSON_ENDPOINT : SettingsEnum::PIPEDRIVE_ORGANIZATION_ENDPOINT);

        return $this->getClient()->request('POST', $url, [
                'json' => $payload,
            ]);
    }

    public function update(string $objectName, array $payload, $integrationId): ResponseInterface
    {
        $url      = $this->getUrl(MappingManualFactory::CONTACT_OBJECT === $objectName ? SettingsEnum::PIPEDRIVE_PERSON_ENDPOINT : SettingsEnum::PIPEDRIVE_ORGANIZATION_ENDPOINT).'/'.$integrationId;

        return $this->getClient()->request('PUT', $url, [
                    'json' => $payload,
                ]);
    }

    public function search(string $objectName, array $data)
    {
        $payload = [
            'exact_match' => 'true',
        ];
        $url      = $this->getUrl(MappingManualFactory::CONTACT_OBJECT === $objectName ? SettingsEnum::PIPEDRIVE_PERSON_ENDPOINT : SettingsEnum::PIPEDRIVE_ORGANIZATION_ENDPOINT).'/search';

        if (MappingManualFactory::CONTACT_OBJECT === $objectName) {
            $payload['term']   = InputHelper::email($data['email']);
            $payload['fields'] = 'email';
        } else {
            $payload['term']   = InputHelper::string($data['name']);
            $payload['fields'] = 'name';
        }

        return $this->getClient()->request(Request::METHOD_GET, $url, ['query' => $payload]);
    }

    public function deleteBatch(string $objectName, array $objectIds): bool
    {
        $chunkObjectIds = array_chunk($objectIds, 400);

        foreach ($chunkObjectIds as $objectIds) {
            $url    = $this->getUrl((MappingManualFactory::CONTACT_OBJECT === $objectName ? SettingsEnum::PIPEDRIVE_PERSON_ENDPOINT : SettingsEnum::PIPEDRIVE_ORGANIZATION_ENDPOINT));
            try {
                $this->getClient()->request('DELETE', $url, ['query' => ['ids' => $objectIds]]);
            } catch (RequestException $e) {
                $this->logger->error(
                    sprintf('Can not delete %s integrationIds:%s with error %s', $objectName, json_encode($objectIds), $e->getMessage())
                );

                return false;
            }
        }

        return true;
    }

    public function getFields(string $objectName): ?array
    {
        switch ($objectName) {
            case MappingManualFactory::CONTACT_OBJECT:
                $url = $this->getUrl(SettingsEnum::PIPEDRIVE_PERSON_FIELD_ENDPOINT);
                break;

            case MappingManualFactory::COMPANY_OBJECT:
                $url = $this->getUrl(SettingsEnum::PIPEDRIVE_ORGANIZATION_FIELD_ENDPOINT);
                break;

            default:
                throw new PipedriveBundleMappingException(sprintf('Unknow object %s', $objectName));
        }

        $data      = [];
        $nextStart = 0;
        while (true) {
            $response = new Response($this->getClient()->request('GET', $url, ['start' => $nextStart]));

            if ($response->hasError()) {
                $this->logger->error(
                    sprintf(
                        '%s: Error fetching %s fields: %s',
                        Pipedrive2Integration::DISPLAY_NAME,
                        $objectName,
                        $response->getError()
                    )
                );

                return $data;
            }

            $data = array_merge($data, $response->getData());

            if (!$nextStart = $response->getNextStart()) {
                break;
            }
        }

        return $data;
    }

    /**
     * Used by AuthSupport to exchange a code for tokens.
     */
    public function exchangeCodeForToken(string $code, string $state): void
    {
        $client      = $this->getClientForAuthorization($code, $state);
        $credentials = $this->getCredentials($code, $state);
        // Force the client to make a call so that the Guzzle middleware will exchange the code for an access token

        $params = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'state'         => $state,
            'redirect_uri'  => $this->getRedirectUri(),
            'client_id'     => $credentials->getClientId(),
            'client_secret' => $credentials->getClientSecret(),
        ];
        try {
            $client->request(
                'POST',
                SettingsEnum::PIPEDRIVE_TOKEN_EXCHANGE_URL,
                [
                    'form_params' => $params,
                ]
            );
        } catch (RequestException $requestException) {
            $this->logger->error($requestException->getMessage());
        }
    }

    /**
     * @throws PluginNotConfiguredException
     * @throws InvalidCredentialsException
     * @throws IntegrationNotFoundException
     */
    public function getClient(): ClientInterface
    {
        $credentials = $this->getCredentials();
        $config      = $this->getConfig();

        return $this->httpFactory->getClient($credentials, $config);
    }

    /**
     * @throws IntegrationNotFoundException
     * @throws InvalidCredentialsException
     * @throws PluginNotConfiguredException
     */
    private function getClientForAuthorization(?string $code = null, ?string $state = null): ClientInterface
    {
        $credentials = $this->getCredentials($code, $state);
        $config      = $this->getConfig();

        return $this->httpFactory->getClient($credentials, $config);
    }

    /**
     * @throws PluginNotConfiguredException
     */
    private function getCredentials(?string $code = null, ?string $state = null): Credentials
    {
        if (!$this->config->isConfigured()) {
            throw new PluginNotConfiguredException();
        }
        $apiKeys = $this->config->getApiKeys();

        $redirectUri = $this->getRedirectUri();

        return new Credentials(SettingsEnum::PIPEDRIVE_TOKEN_EXCHANGE_URL, $redirectUri, $apiKeys['client_id'], $apiKeys['client_secret'], $code, $state);
    }

    /**
     * @throws IntegrationNotFoundException
     */
    private function getConfig(): ConnectionConfig
    {
        $this->connectionConfig->setIntegrationConfiguration($this->config->getIntegrationEntity());

        return $this->connectionConfig;
    }

    private function getRedirectUri(): string
    {
        return $this->router->generate(
            'mautic_integration_public_callback',
            ['integration' => Pipedrive2Integration::NAME],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function logError(string $method, array $args, ?string $error)
    {
        $this->logger->error(sprintf('%s %s: %s', __METHOD__, json_encode(func_get_args()), $error));
    }

    protected function getDataByDateRange(
        Response $response,
        ?\DateTimeInterface $startDateTime,
        ?\DateTimeInterface $endDateTime
    ): array {
        $data = $response->getData();
        if ($startDateTime || $endDateTime) {
            foreach ($data as $key => $datum) {
                if (empty($datum[SettingsEnum::UPDATE_TIME])) {
                    unset($data[$key]);
                    continue;
                }
                $updateTime = \DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $datum[SettingsEnum::UPDATE_TIME],
                    new \DateTimeZone('UTC')
                );
                if ($startDateTime && $startDateTime > $updateTime) {
                    unset($data[$key]);
                    continue;
                }
                if ($endDateTime && $endDateTime < $updateTime) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    public function getUrl(string $endpoint): string
    {
        return $this->apiUrl.$endpoint;
    }

    /**
     * @throws PipedriveBundleMappingException
     */
    protected function getAllUrl(string $objectName): string
    {
        switch ($objectName) {
            case MappingManualFactory::CONTACT_OBJECT:
                $url = $this->getUrl(SettingsEnum::PIPEDRIVE_PERSON_ENDPOINT);
                break;
            case MappingManualFactory::COMPANY_OBJECT:
                $url = $this->getUrl(SettingsEnum::PIPEDRIVE_ORGANIZATION_ENDPOINT);
                break;

            default:
                throw new PipedriveBundleMappingException(sprintf('Unknow object %s', $objectName));
        }

        return $url;
    }

    /**
     * @return array|Response
     *
     * @throws GuzzleException
     * @throws IntegrationNotFoundException
     * @throws InvalidCredentialsException
     * @throws PipedriveBundleMappingException
     * @throws PluginNotConfiguredException
     */
    public function getForPage(
        string $objectName,
        int $limit,
        int $page,
        ?\DateTimeInterface $startDateTime = null,
        ?\DateTimeInterface $endDateTime = null
    ) {
        $url = $this->getAllUrl($objectName);

        $queryParams = [
            'limit' => $limit,
            'start' => 1 === $page ? 0 : ($page * $limit) - 500,
        ];
        if ($startDateTime || $endDateTime) {
            $queryParams['sort'] = 'update_time DESC';
        }

        $response = new Response($this->getClient()->request('GET', $url, ['query' => $queryParams]));
        if ($response->hasError()) {
            $this->logger->error(
                sprintf(
                    '%s: Error fetching %s objects: %s',
                    Pipedrive2Integration::DISPLAY_NAME,
                    $objectName,
                    $response->getError()
                )
            );

            return [];
        }

        return $response;
    }
}
