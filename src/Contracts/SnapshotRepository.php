<?php

declare(strict_types=1);

namespace LaraTimeCode\Contracts;

interface SnapshotRepository
{
    /** @param array<string, mixed> $snapshot */
    public function save(array $snapshot): string;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /** @return list<array<string, mixed>> */
    public function all(): array;

    public function delete(string $id): bool;

    public function prune(int $retentionDays, int $maxFiles): int;
}
