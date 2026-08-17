<?php

declare(strict_types=1);

namespace LaraTimeCode\Capture;

final class CaptureContext
{
    private bool $active = false;

    /** @var list<array<string, mixed>> */
    private array $queries = [];

    /** @var list<array<string, mixed>> */
    private array $outboundHttp = [];

    public function start(): void
    {
        $this->queries = [];
        $this->outboundHttp = [];
        $this->active = true;
    }

    public function stop(): void
    {
        $this->active = false;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @param array<string, mixed> $query */
    public function addQuery(array $query, int $limit): void
    {
        if ($this->active && count($this->queries) < $limit) {
            $this->queries[] = $query;
        }
    }

    /** @param array<string, mixed> $exchange */
    public function addOutboundHttp(array $exchange, int $limit = 50): void
    {
        if ($this->active && count($this->outboundHttp) < $limit) {
            $this->outboundHttp[] = $exchange;
        }
    }

    /** @return array{queries: list<array<string, mixed>>, outbound_http: list<array<string, mixed>>} */
    public function snapshot(): array
    {
        return [
            'queries' => $this->queries,
            'outbound_http' => $this->outboundHttp,
        ];
    }
}
