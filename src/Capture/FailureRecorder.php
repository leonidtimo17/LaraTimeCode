<?php

declare(strict_types=1);

namespace LaraTimeCode\Capture;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LaraTimeCode\Contracts\SnapshotRepository;
use LaraTimeCode\Redaction\Redactor;
use LaraTimeCode\Support\SnapshotId;
use Throwable;

final class FailureRecorder
{
    public function __construct(
        private readonly SnapshotRepository $snapshots,
        private readonly Redactor $redactor,
        private readonly CaptureContext $context,
        private readonly Config $config,
        private readonly Application $app,
    ) {}

    public function shouldCapture(Request $request, Throwable $exception): bool
    {
        if (! (bool) $this->config->get('laratimecode.enabled', false)) {
            return false;
        }

        if ($request->attributes->getBoolean('_laratimecode_replay')) {
            return false;
        }

        foreach ((array) $this->config->get('laratimecode.capture.ignore_exceptions', []) as $class) {
            if (is_string($class) && $exception instanceof $class) {
                return false;
            }
        }

        foreach ((array) $this->config->get('laratimecode.capture.exclude_paths', []) as $pattern) {
            if (is_string($pattern) && Str::is($pattern, $request->path())) {
                return false;
            }
        }

        $sampleRate = min(1.0, max(0.0, (float) $this->config->get(
            'laratimecode.capture.sample_rate',
            1.0,
        )));

        return $sampleRate >= 1.0 || random_int(1, 1_000_000) <= (int) ($sampleRate * 1_000_000);
    }

    public function capture(Request $request, Throwable $exception): string
    {
        $capturedAt = new DateTimeImmutable('now');
        $id = SnapshotId::generate($capturedAt);
        $route = $request->route();
        $user = $request->user();

        $snapshot = [
            'schema' => 1,
            'id' => $id,
            'captured_at' => $capturedAt->format(DATE_ATOM),
            'application' => $this->applicationContext(),
            'request' => [
                'method' => $request->getMethod(),
                'uri' => '/'.ltrim($request->path(), '/'),
                'query' => $this->redactor->redact($request->query->all(), 'query'),
                'input' => $this->redactor->redact($this->requestInput($request), 'input'),
                'headers' => $this->capturedHeaders($request),
                'ip' => (bool) $this->config->get('laratimecode.capture.include_ip', false)
                    ? $request->ip()
                    : null,
            ],
            'route' => [
                'name' => is_object($route) && method_exists($route, 'getName') ? $route->getName() : null,
                'action' => is_object($route) && method_exists($route, 'getActionName')
                    ? $route->getActionName()
                    : null,
                'parameters' => is_object($route) && method_exists($route, 'parameters')
                    ? $this->redactor->redact($route->parameters(), 'route.parameters')
                    : [],
            ],
            'auth' => $user === null ? null : [
                'guard' => (string) $this->config->get('laratimecode.capture.auth_guard', 'web'),
                'class' => $user::class,
                'identifier' => $this->redactor->redact($user->getAuthIdentifier(), 'auth.identifier'),
            ],
            'exception' => $this->exceptionContext($exception),
            'execution' => $this->context->snapshot(),
        ];

        $storedId = $this->snapshots->save($snapshot);
        $this->snapshots->prune(
            (int) $this->config->get('laratimecode.storage.retention_days', 14),
            (int) $this->config->get('laratimecode.storage.max_files', 100),
        );

        return $storedId;
    }

    /** @return array<string, mixed> */
    private function applicationContext(): array
    {
        $composerLock = base_path('composer.lock');

        return [
            'name' => (string) config('app.name', 'Laravel'),
            'environment' => $this->app->environment(),
            'laravel_version' => $this->app->version(),
            'php_version' => PHP_VERSION,
            'composer_lock_hash' => is_file($composerLock) ? hash_file('sha256', $composerLock) : null,
            'release' => $this->config->get('laratimecode.release'),
        ];
    }

    /** @return array<string, mixed> */
    private function exceptionContext(Throwable $exception): array
    {
        $trace = [];

        if ((bool) $this->config->get('laratimecode.capture.include_trace', true)) {
            $limit = (int) $this->config->get('laratimecode.capture.trace_frames', 40);

            foreach (array_slice($exception->getTrace(), 0, max(0, $limit)) as $frame) {
                $trace[] = array_filter([
                    'file' => isset($frame['file']) ? $this->relativePath((string) $frame['file']) : null,
                    'line' => $frame['line'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'type' => $frame['type'] ?? null,
                    'function' => $frame['function'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            }
        }

        return [
            'class' => $exception::class,
            'message' => $this->redactor->redact($exception->getMessage(), 'exception.message'),
            'code' => $exception->getCode(),
            'file' => $this->relativePath($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ];
    }

    /** @return array<string, mixed> */
    private function capturedHeaders(Request $request): array
    {
        $headers = [];

        foreach ((array) $this->config->get('laratimecode.capture.headers', []) as $name) {
            if (is_string($name) && $request->headers->has($name)) {
                $headers[$name] = $request->headers->get($name);
            }
        }

        return $this->redactor->redact($headers, 'headers');
    }

    /** @return array<string, mixed> */
    private function requestInput(Request $request): array
    {
        $input = $request->isJson()
            ? $request->json()->all()
            : $request->request->all();

        return array_replace_recursive($input, $request->allFiles());
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath) ? substr($path, strlen($basePath)) : $path;
    }
}
