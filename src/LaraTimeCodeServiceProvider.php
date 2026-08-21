<?php

declare(strict_types=1);

namespace LaraTimeCode;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use LaraTimeCode\Capture\CaptureContext;
use LaraTimeCode\Capture\FailureRecorder;
use LaraTimeCode\Capture\FrameworkEventRecorder;
use LaraTimeCode\Commands\DeleteTimeCodeCommand;
use LaraTimeCode\Commands\ListTimeCodesCommand;
use LaraTimeCode\Commands\MakeTimeCodeTestCommand;
use LaraTimeCode\Commands\ReplayTimeCodeCommand;
use LaraTimeCode\Commands\ShowTimeCodeCommand;
use LaraTimeCode\Contracts\SnapshotRepository;
use LaraTimeCode\Http\Middleware\CaptureFailures;
use LaraTimeCode\Redaction\Redactor;
use LaraTimeCode\Storage\FileSnapshotRepository;
use Psr\Log\LoggerInterface;
use Throwable;

final class LaraTimeCodeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laratimecode.php', 'laratimecode');

        $this->app->scoped(CaptureContext::class, static fn (): CaptureContext => new CaptureContext);
        $this->app->scoped(FrameworkEventRecorder::class);

        $this->app->singleton(Redactor::class, function (): Redactor {
            return new Redactor(
                patterns: (array) config('laratimecode.redaction.fields', []),
                replacement: (string) config('laratimecode.redaction.replacement', '[REDACTED]'),
                maxStringLength: (int) config('laratimecode.capture.max_string_length', 16_384),
            );
        });

        $this->app->singleton(SnapshotRepository::class, function ($app): SnapshotRepository {
            return new FileSnapshotRepository(
                files: $app->make('files'),
                encrypter: $app->make('encrypter'),
                directory: (string) config('laratimecode.storage.path'),
                encrypt: (bool) config('laratimecode.storage.encrypt', true),
                logger: $app->make(LoggerInterface::class),
            );
        });
    }

    public function boot(Dispatcher $events): void
    {
        $this->publishes([
            __DIR__.'/../config/laratimecode.php' => config_path('laratimecode.php'),
        ], 'laratimecode-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DeleteTimeCodeCommand::class,
                ListTimeCodesCommand::class,
                MakeTimeCodeTestCommand::class,
                ReplayTimeCodeCommand::class,
                ShowTimeCodeCommand::class,
            ]);
        }

        if ((bool) config('laratimecode.enabled', false)) {
            $events->listen(QueryExecuted::class, function (QueryExecuted $event): void {
                $this->app->make(FrameworkEventRecorder::class)->recordQuery($event);
            });

            if (class_exists(ResponseReceived::class)) {
                $events->listen(ResponseReceived::class, function (ResponseReceived $event): void {
                    $this->app->make(FrameworkEventRecorder::class)->recordResponse($event);
                });
            }

            if (class_exists(ConnectionFailed::class)) {
                $events->listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
                    $this->app->make(FrameworkEventRecorder::class)->recordConnectionFailure($event);
                });
            }
        }

        $this->app->booted(function (): void {
            $this->registerMiddleware();
            $this->registerExceptionHook();
        });
    }

    private function registerMiddleware(): void
    {
        if (! $this->app->bound(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(CaptureFailures::class);
        }
    }

    private function registerExceptionHook(): void
    {
        if (! $this->app->bound(ExceptionHandler::class)) {
            return;
        }

        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (Throwable $exception, Request $request): void {
            $this->app->make(FailureRecorder::class)->captureIfNeeded($request, $exception);
        });
    }
}
