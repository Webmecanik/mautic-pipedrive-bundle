<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Connection\DTO;

use Psr\Http\Message\ResponseInterface;

class Response
{
    private int $code;

    private array $data;

    private ?string $error;

    private ?int $nextStart;

    public function __construct(ResponseInterface $response)
    {
        $result          = json_decode($response->getBody()->getContents(), true);
        $this->data      = $result['data'] ?? [];
        $this->error     = $result['error'] ?? null;
        $this->code      = $this->error ? 403 : 200;
        $this->nextStart = $result['additional_data']['pagination']['next_start'] ?? null;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return isset($this->error);
    }

    public function getNextStart(): ?int
    {
        return $this->nextStart;
    }

    public function hasNext(): bool
    {
        return isset($this->nextStart);
    }
}
