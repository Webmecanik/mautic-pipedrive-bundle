<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Connection;

use GuzzleHttp\Exception\RequestException;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\PipedriveBundle\Connection\DTO\Response;
use Symfony\Component\HttpFoundation\Request;

class Owners
{
    private Client $client;

    private ?array $pipedriveOwners;

    private UserModel $userModel;

    public function __construct(Client $client, UserModel $userModel)
    {
        $this->client    = $client;
        $this->userModel = $userModel;
    }

    public function getInternalIdFromEmail(?string $ownerEmail)
    {
        if ($ownerInternal = $this->userModel->getRepository()->findOneBy(['email' => $ownerEmail])) {
            return $ownerInternal->getId();
        }

        return null;
    }

    public function getPipedriveId($ownerId = null)
    {
        if ($owner = $this->userModel->getEntity($ownerId)) {
            return $this->findIdByEmailOnPipedrive($owner->getEmail());
        }

        return null;
    }

    private function findIdByEmailOnPipedrive(string $email)
    {
        $this->fetchPipedriveOwners();

        foreach ($this->pipedriveOwners as $pipedriveOwner) {
            if ($pipedriveOwner['email'] === $email) {
                return $pipedriveOwner['id'];
            }
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException
     * @throws \Mautic\IntegrationsBundle\Exception\InvalidCredentialsException
     * @throws \Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException
     */
    protected function fetchPipedriveOwners(): void
    {
        if (!isset($this->pipedriveOwners)) {
            $uri = $this->client->getUrl('users');

            try {
                $response              = new Response($this->client->getClient()->request(Request::METHOD_GET, $uri));
                $this->pipedriveOwners = $response->getData() ?? [];
            } catch (RequestException $requestException) {
                $this->pipedriveOwners = [];
            }
        }
    }
}
