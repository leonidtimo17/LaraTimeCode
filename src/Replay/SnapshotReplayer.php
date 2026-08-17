<?php

declare(strict_types=1);

namespace LaraTimeCode\Replay;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SnapshotReplayer
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly AuthFactory $auth,
        private readonly Config $config,
        private readonly Application $app,
    ) {}

    /** @param array<string, mixed> $snapshot */
    public function replay(array $snapshot, ?bool $restoreAuth = null): ReplayResult
    {
        $requestData = (array) ($snapshot['request'] ?? []);
        $method = strtoupper((string) ($requestData['method'] ?? 'GET'));
        $uri = (string) ($requestData['uri'] ?? '/');
        $query = is_array($requestData['query'] ?? null) ? $requestData['query'] : [];
        $input = is_array($requestData['input'] ?? null) ? $requestData['input'] : [];
        $headers = is_array($requestData['headers'] ?? null) ? $requestData['headers'] : [];
        $queryString = $query === [] ? '' : '?'.http_build_query($query);
        $server = $this->serverVariables($headers);
        $contentType = strtolower((string) ($headers['content-type'] ?? ''));
        $content = null;
        $parameters = $input;

        if (str_contains($contentType, 'application/json')) {
            $content = json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $parameters = [];
        }

        $request = Request::create($uri.$queryString, $method, $parameters, [], [], $server, $content);
        $request->attributes->set('_laratimecode_replay', true);
        $request->attributes->set('_laratimecode_id', $snapshot['id'] ?? null);

        $restoreAuth ??= (bool) $this->config->get('laratimecode.replay.restore_auth_user', false);

        if ($restoreAuth) {
            $this->restoreAuthUser(is_array($snapshot['auth'] ?? null) ? $snapshot['auth'] : null);
        }

        $startedAt = hrtime(true);
        $response = null;
        $exception = null;
        $originalExceptionHandler = $this->app->make(ExceptionHandler::class);
        $this->app->instance(
            ExceptionHandler::class,
            new RethrowingExceptionHandler($originalExceptionHandler),
        );

        try {
            $response = $this->kernel->handle($request);
            $trappedException = $request->attributes->get('_laratimecode_exception');

            if ($trappedException instanceof Throwable) {
                $exception = $trappedException;
            }
        } catch (Throwable $throwable) {
            $exception = $throwable;
        } finally {
            $this->app->instance(ExceptionHandler::class, $originalExceptionHandler);

            if ($response instanceof Response) {
                $this->kernel->terminate($request, $response);
            }

            if (method_exists($this->auth, 'forgetGuards')) {
                $this->auth->forgetGuards();
            }
        }

        return new ReplayResult(
            expectedException: is_string($snapshot['exception']['class'] ?? null)
                ? $snapshot['exception']['class']
                : null,
            actualException: $exception,
            responseStatus: $response?->getStatusCode(),
            responseBody: is_string($response?->getContent()) ? $response->getContent() : null,
            durationMs: (hrtime(true) - $startedAt) / 1_000_000,
        );
    }

    /** @param array<string, mixed>|null $auth */
    private function restoreAuthUser(?array $auth): void
    {
        if ($auth === null || ! is_string($auth['class'] ?? null)) {
            return;
        }

        $class = $auth['class'];
        $identifier = $auth['identifier'] ?? null;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class) || $identifier === null) {
            return;
        }

        /** @var Model $model */
        $model = new $class;
        $user = $model->newQuery()->find($identifier);

        if ($user instanceof Authenticatable) {
            $this->auth->guard((string) ($auth['guard'] ?? 'web'))->setUser($user);
        }
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function serverVariables(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $normalized = strtoupper(str_replace('-', '_', (string) $name));
            $key = in_array($normalized, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)
                ? $normalized
                : 'HTTP_'.$normalized;
            $server[$key] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $server;
    }
}
