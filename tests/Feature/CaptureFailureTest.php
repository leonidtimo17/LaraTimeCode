<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraTimeCode\Contracts\SnapshotRepository;
use LaraTimeCode\Replay\SnapshotReplayer;
use LaraTimeCode\Tests\TestCase;
use RuntimeException;

final class CaptureFailureTest extends TestCase
{
    public function test_it_captures_a_failed_request(): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/timecode-test?source=checkout', [
                'order_id' => 42,
                'password' => 'do-not-store-me',
            ]);
        } catch (RuntimeException $exception) {
            self::assertSame('The checkout exploded.', $exception->getMessage());
        }

        $snapshots = $this->app->make(SnapshotRepository::class)->all();

        self::assertCount(1, $snapshots);
        self::assertSame('POST', $snapshots[0]['request']['method']);
        self::assertSame(42, $snapshots[0]['request']['input']['order_id']);
        self::assertSame('[REDACTED]', $snapshots[0]['request']['input']['password']);
        self::assertArrayNotHasKey('source', $snapshots[0]['request']['input']);
        self::assertSame('checkout', $snapshots[0]['request']['query']['source']);
        self::assertSame(RuntimeException::class, $snapshots[0]['exception']['class']);
    }

    public function test_it_replays_the_original_exception_without_recapturing_it(): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/timecode-test', ['order_id' => 42]);
        } catch (RuntimeException) {
            // The first execution creates the snapshot used by the replayer below.
        }

        $this->withExceptionHandling();

        $repository = $this->app->make(SnapshotRepository::class);
        $snapshot = $repository->all()[0];
        $result = $this->app->make(SnapshotReplayer::class)->replay($snapshot);

        self::assertTrue($result->reproduced());
        self::assertInstanceOf(RuntimeException::class, $result->actualException);
        self::assertCount(1, $repository->all());
    }

    public function test_capture_is_disabled_by_default_switch(): void
    {
        $this->app['config']->set('laratimecode.enabled', false);
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/timecode-test', ['order_id' => 42]);
        } catch (RuntimeException) {
            // LaraTimeCode must leave the original exception untouched.
        }

        self::assertSame([], $this->app->make(SnapshotRepository::class)->all());
    }

    public function test_it_records_redacted_outbound_http_context(): void
    {
        Http::fake([
            'api.example.test/*' => Http::response(['token' => 'response-secret'], 200),
        ]);
        $this->withoutExceptionHandling();

        try {
            $this->get('/timecode-http-test');
        } catch (RuntimeException) {
            // The outbound exchange is attached to the failure snapshot.
        }

        $snapshot = $this->app->make(SnapshotRepository::class)->all()[0];
        $exchange = $snapshot['execution']['outbound_http'][0];

        self::assertSame('GET', $exchange['method']);
        self::assertSame('https://api.example.test/orders?token=%5BREDACTED%5D', $exchange['url']);
        self::assertSame(200, $exchange['status']);
        self::assertArrayNotHasKey('response_body', $exchange);
    }

    public function test_it_records_database_query_context_without_bindings(): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->get('/timecode-db-test');
        } catch (RuntimeException) {
            // The SQL metadata is attached to the failure snapshot.
        }

        $snapshot = $this->app->make(SnapshotRepository::class)->all()[0];
        $query = $snapshot['execution']['queries'][0];

        self::assertSame('select 1 as one', $query['sql']);
        self::assertArrayHasKey('time_ms', $query);
        self::assertArrayHasKey('connection', $query);
        self::assertArrayNotHasKey('bindings', $query);
    }
}
