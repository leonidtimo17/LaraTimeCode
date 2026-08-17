<?php

declare(strict_types=1);

namespace LaraTimeCode\Commands;

use Illuminate\Console\Command;
use LaraTimeCode\Contracts\SnapshotRepository;

final class ShowTimeCodeCommand extends Command
{
    protected $signature = 'timecode:show {id : Snapshot identifier}';

    protected $description = 'Show one LaraTimeCode snapshot';

    public function handle(SnapshotRepository $snapshots): int
    {
        $snapshot = $snapshots->find((string) $this->argument('id'));

        if ($snapshot === null) {
            $this->components->error('Snapshot not found.');

            return self::FAILURE;
        }

        $this->line((string) json_encode(
            $snapshot,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        return self::SUCCESS;
    }
}
