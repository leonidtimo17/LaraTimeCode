<?php

declare(strict_types=1);

namespace LaraTimeCode\Replay;

use Throwable;

final readonly class ReplayResult
{
    public function __construct(
        public ?string $expectedException,
        public ?Throwable $actualException,
        public ?int $responseStatus,
        public ?string $responseBody,
        public float $durationMs,
    ) {}

    public function reproduced(): bool
    {
        return $this->expectedException !== null
            && $this->actualException instanceof $this->expectedException;
    }
}
