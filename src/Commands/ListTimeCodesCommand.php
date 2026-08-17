<?php

declare(strict_types=1);

namespace LaraTimeCode\Commands;

use Illuminate\Console\Command;
use LaraTimeCode\Contracts\SnapshotRepository;

final class ListTimeCodesCommand extends Command
{
    protected $signature = 'timecode:list {--limit=20 : Maximum snapshots to display}';

    protected $description = 'List captured LaraTimeCode snapshots';

    public function handle(SnapshotRepository $snapshots): int
    {
        $rows = [];
        $limit = max(1, (int) $this->option('limit'));

        foreach (array_slice($snapshots->all(), 0, $limit) as $snapshot) {
            $rows[] = [
                $snapshot['id'] ?? 'unknown',
                $snapshot['captured_at'] ?? 'unknown',
                trim(($snapshot['request']['method'] ?? '').' '.($snapshot['request']['uri'] ?? '')),
                $snapshot['exception']['class'] ?? 'unknown',
                $snapshot['exception']['message'] ?? '',
            ];
        }

        if ($rows === []) {
            $this->components->info('No LaraTimeCode snapshots have been captured.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Captured', 'Request', 'Exception', 'Message'], $rows);

        return self::SUCCESS;
    }
}
