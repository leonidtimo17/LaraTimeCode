<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Unit;

use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use LaraTimeCode\Storage\FileSnapshotRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class FileSnapshotRepositoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/laratimecode-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_stores_and_loads_encrypted_snapshots(): void
    {
        $files = new Filesystem;
        $repository = new FileSnapshotRepository(
            $files,
            new Encrypter(str_repeat('k', 32), 'AES-256-CBC'),
            $this->directory,
            true,
        );
        $snapshot = [
            'id' => '20260817-120000-123456-abcd1234',
            'captured_at' => '2026-08-17T12:00:00+09:00',
            'exception' => ['class' => 'RuntimeException'],
        ];

        $repository->save($snapshot);

        self::assertStringStartsWith(
            'LTC1:',
            $files->get($this->directory.'/'.$snapshot['id'].'.repro'),
        );
        self::assertSame($snapshot, $repository->find($snapshot['id']));
    }

    public function test_it_skips_snapshots_it_cannot_read_and_reports_them(): void
    {
        $files = new Filesystem;
        $logger = new class extends AbstractLogger
        {
            /** @var list<string> */
            public array $warnings = [];

            public function log($level, $message, array $context = []): void
            {
                if ($level === 'warning') {
                    $this->warnings[] = (string) ($context['path'] ?? '');
                }
            }
        };

        $repository = new FileSnapshotRepository(
            $files,
            new Encrypter(str_repeat('k', 32), 'AES-256-CBC'),
            $this->directory,
            true,
            $logger,
        );
        $repository->save(['id' => 'readable-snapshot', 'captured_at' => '2026-08-17T12:00:00+00:00']);

        $foreign = new FileSnapshotRepository(
            $files,
            new Encrypter(str_repeat('z', 32), 'AES-256-CBC'),
            $this->directory,
            true,
        );
        $foreign->save(['id' => 'foreign-key-snapshot', 'captured_at' => '2026-08-18T12:00:00+00:00']);

        $files->put($this->directory.'/malformed-snapshot.repro', 'not json at all');

        $snapshots = $repository->all();

        self::assertCount(1, $snapshots);
        self::assertSame('readable-snapshot', $snapshots[0]['id']);
        self::assertCount(2, $logger->warnings);
    }
}
