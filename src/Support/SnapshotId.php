<?php

declare(strict_types=1);

namespace LaraTimeCode\Support;

use DateTimeInterface;

final class SnapshotId
{
    public static function generate(DateTimeInterface $capturedAt): string
    {
        return sprintf(
            '%s-%s',
            $capturedAt->format('Ymd-His-u'),
            bin2hex(random_bytes(4)),
        );
    }

    public static function isValid(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1;
    }
}
