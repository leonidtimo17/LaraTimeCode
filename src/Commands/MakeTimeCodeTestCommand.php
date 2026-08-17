<?php

declare(strict_types=1);

namespace LaraTimeCode\Commands;

use Illuminate\Console\Command;
use LaraTimeCode\Contracts\SnapshotRepository;
use LaraTimeCode\Replay\PestTestGenerator;
use RuntimeException;

final class MakeTimeCodeTestCommand extends Command
{
    protected $signature = 'timecode:make-test
                            {id : Snapshot identifier}
                            {--name= : Pest test description}
                            {--force : Replace an existing generated test}';

    protected $description = 'Generate a Pest regression test from a LaraTimeCode snapshot';

    public function handle(SnapshotRepository $snapshots, PestTestGenerator $generator): int
    {
        $snapshot = $snapshots->find((string) $this->argument('id'));

        if ($snapshot === null) {
            $this->components->error('Snapshot not found.');

            return self::FAILURE;
        }

        try {
            $path = $generator->generate(
                $snapshot,
                is_string($this->option('name')) ? $this->option('name') : null,
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Pest regression test generated.');
        $this->line($path);

        return self::SUCCESS;
    }
}
