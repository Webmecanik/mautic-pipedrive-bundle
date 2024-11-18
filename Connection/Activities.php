<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Connection;

use Symfony\Component\HttpFoundation\Request;

class Activities
{
    public function __construct(private Client $client)
    {
    }

    public function addActivity(array $data): void
    {
        $this->client->getClient()->request(
            Request::METHOD_POST,
            $this->client->getUrl('activities'),
            ['json' => $data]
        );
    }
}
