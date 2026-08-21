<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LaraTimeCode\Contracts\SnapshotRepository;
use LaraTimeCode\Replay\SnapshotReplayer;
use LaraTimeCode\Tests\Fixtures\ReplayUser;
use LaraTimeCode\Tests\TestCase;
use RuntimeException;

final class SnapshotReplayerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('replay_users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email');
        });

        ReplayUser::query()->create(['email' => 'person@example.com']);
    }

    public function test_it_restores_the_captured_user_from_configuration(): void
    {
        $this->app['config']->set('laratimecode.replay.restore_auth_user', true);

        $result = $this->app->make(SnapshotReplayer::class)->replay($this->snapshot());

        self::assertSame('Authenticated as 1', $result->actualException?->getMessage());
    }

    public function test_it_leaves_the_replay_unauthenticated_by_default(): void
    {
        $result = $this->app->make(SnapshotReplayer::class)->replay($this->snapshot());

        self::assertSame('Authenticated as guest', $result->actualException?->getMessage());
    }

    public function test_an_explicit_argument_overrides_the_configuration(): void
    {
        $this->app['config']->set('laratimecode.replay.restore_auth_user', true);

        $result = $this->app->make(SnapshotReplayer::class)->replay($this->snapshot(), false);

        self::assertSame('Authenticated as guest', $result->actualException?->getMessage());
    }

    public function test_the_replay_command_honours_the_configured_auth_restore(): void
    {
        $this->app['config']->set('laratimecode.replay.restore_auth_user', true);
        $this->app->make(SnapshotRepository::class)->save($this->snapshot());

        $this->artisan('timecode:replay', ['id' => '20260817-120000-123456-abcd1234'])
            ->expectsOutputToContain('Authenticated as 1')
            ->assertExitCode(Command::SUCCESS);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'id' => '20260817-120000-123456-abcd1234',
            'captured_at' => '2026-08-17T12:00:00+00:00',
            'request' => [
                'method' => 'GET',
                'uri' => '/timecode-auth-test',
                'query' => [],
                'input' => [],
                'headers' => [],
            ],
            'auth' => [
                'guard' => 'web',
                'class' => ReplayUser::class,
                'identifier' => 1,
            ],
            'exception' => ['class' => RuntimeException::class],
        ];
    }
}
