<?php

declare(strict_types=1);

namespace LaraTimeCode\Commands;

use Illuminate\Console\Command;
use LaraTimeCode\Contracts\SnapshotRepository;
use LaraTimeCode\Replay\SnapshotReplayer;

final class ReplayTimeCodeCommand extends Command
{
    protected $signature = 'timecode:replay
                            {id : Snapshot identifier}
                            {--auth : Restore the captured Eloquent user when available}';

    protected $description = 'Replay a captured request inside the current Laravel application';

    public function handle(SnapshotRepository $snapshots, SnapshotReplayer $replayer): int
    {
        $snapshot = $snapshots->find((string) $this->argument('id'));

        if ($snapshot === null) {
            $this->components->error('Snapshot not found.');

            return self::FAILURE;
        }

        $result = $replayer->replay($snapshot, (bool) $this->option('auth'));
        $this->components->twoColumnDetail('Duration', number_format($result->durationMs, 2).' ms');
        $this->components->twoColumnDetail('Expected exception', $result->expectedException ?? 'none');
        $this->components->twoColumnDetail(
            'Actual result',
            $result->actualException !== null
                ? $result->actualException::class.': '.$result->actualException->getMessage()
                : 'HTTP '.($result->responseStatus ?? 'no response'),
        );

        if ($result->reproduced()) {
            $this->components->warn('The original exception was reproduced.');

            return self::SUCCESS;
        }

        if ($result->actualException !== null) {
            $this->components->error('Replay failed with a different exception.');

            return self::FAILURE;
        }

        $this->components->info('The original exception was not reproduced.');

        return self::FAILURE;
    }
}
