<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Connection;

use Symfony\Component\HttpFoundation\Request;

class Activities
{
    private ?array $activities = null;

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function addActivity(array $data)
    {
        $this->client->getClient()->request(
            Request::METHOD_POST,
            $this->client->getUrl('activities'),
            ['json' => $data]
        );
    }
}
