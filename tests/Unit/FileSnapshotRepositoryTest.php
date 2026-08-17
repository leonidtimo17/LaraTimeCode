<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Unit;

use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use LaraTimeCode\Storage\FileSnapshotRepository;
use PHPUnit\Framework\TestCase;

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
}
