<?php

declare(strict_types=1);

namespace LaraTimeCode\Storage;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use LaraTimeCode\Contracts\SnapshotRepository;
use LaraTimeCode\Support\SnapshotId;
use RuntimeException;

final class FileSnapshotRepository implements SnapshotRepository
{
    private const ENCRYPTED_PREFIX = 'LTC1:';

    public function __construct(
        private readonly Filesystem $files,
        private readonly Encrypter $encrypter,
        private readonly string $directory,
        private readonly bool $encrypt = true,
    ) {}

    public function save(array $snapshot): string
    {
        $id = (string) ($snapshot['id'] ?? '');

        if (! SnapshotId::isValid($id)) {
            throw new RuntimeException('A LaraTimeCode snapshot must contain a valid id.');
        }

        $this->ensureDirectoryExists();

        try {
            $json = json_encode(
                $snapshot,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the LaraTimeCode snapshot.', 0, $exception);
        }

        $contents = $this->encrypt
            ? self::ENCRYPTED_PREFIX.$this->encrypter->encryptString($json)
            : $json;

        $path = $this->path($id);
        $temporaryPath = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        $this->files->put($temporaryPath, $contents, true);

        if (! @rename($temporaryPath, $path)) {
            $this->files->delete($temporaryPath);
            throw new RuntimeException(sprintf('Unable to atomically store snapshot [%s].', $id));
        }

        return $id;
    }

    public function find(string $id): ?array
    {
        if (! SnapshotId::isValid($id)) {
            return null;
        }

        $path = $this->path($id);

        if (! $this->files->exists($path)) {
            return null;
        }

        $contents = $this->files->get($path);

        if (str_starts_with($contents, self::ENCRYPTED_PREFIX)) {
            $contents = $this->encrypter->decryptString(substr($contents, strlen(self::ENCRYPTED_PREFIX)));
        }

        try {
            $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Snapshot [%s] is not valid JSON.', $id), 0, $exception);
        }

        return is_array($snapshot) ? $snapshot : null;
    }

    public function all(): array
    {
        if (! $this->files->isDirectory($this->directory)) {
            return [];
        }

        $snapshots = [];

        foreach ($this->files->glob($this->directory.'/*.repro') ?: [] as $path) {
            $snapshot = $this->find(pathinfo($path, PATHINFO_FILENAME));

            if ($snapshot !== null) {
                $snapshots[] = $snapshot;
            }
        }

        usort(
            $snapshots,
            static fn (array $left, array $right): int => strcmp(
                (string) ($right['captured_at'] ?? ''),
                (string) ($left['captured_at'] ?? ''),
            ),
        );

        return $snapshots;
    }

    public function delete(string $id): bool
    {
        return SnapshotId::isValid($id) && $this->files->delete($this->path($id));
    }

    public function prune(int $retentionDays, int $maxFiles): int
    {
        if (! $this->files->isDirectory($this->directory)) {
            return 0;
        }

        $paths = $this->files->glob($this->directory.'/*.repro') ?: [];
        usort($paths, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        $cutoff = time() - (max(0, $retentionDays) * 86_400);
        $deleted = 0;

        foreach ($paths as $index => $path) {
            $expired = $retentionDays > 0 && filemtime($path) < $cutoff;
            $overLimit = $maxFiles > 0 && $index >= $maxFiles;

            if (($expired || $overLimit) && $this->files->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function ensureDirectoryExists(): void
    {
        if (! $this->files->isDirectory($this->directory)) {
            $this->files->makeDirectory($this->directory, 0700, true);
        }
    }

    private function path(string $id): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$id.'.repro';
    }
}
