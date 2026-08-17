<?php

declare(strict_types=1);

namespace LaraTimeCode\Commands;

use Illuminate\Console\Command;
use LaraTimeCode\Contracts\SnapshotRepository;

final class DeleteTimeCodeCommand extends Command
{
    protected $signature = 'timecode:delete
                            {id : Snapshot identifier}
                            {--force : Delete without confirmation}';

    protected $description = 'Delete a LaraTimeCode snapshot';

    public function handle(SnapshotRepository $snapshots): int
    {
        $id = (string) $this->argument('id');

        if ($snapshots->find($id) === null) {
            $this->components->error('Snapshot not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Delete snapshot [%s]?', $id))) {
            $this->components->info('Snapshot was not deleted.');

            return self::SUCCESS;
        }

        if (! $snapshots->delete($id)) {
            $this->components->error('Snapshot could not be deleted.');

            return self::FAILURE;
        }

        $this->components->info('Snapshot deleted.');

        return self::SUCCESS;
    }
}
