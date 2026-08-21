<?php

declare(strict_types=1);

namespace LaraTimeCode\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use LaraTimeCode\Capture\CaptureContext;
use LaraTimeCode\Capture\FailureRecorder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CaptureFailures
{
    public function __construct(
        private readonly FailureRecorder $recorder,
        private readonly CaptureContext $context,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->getBoolean('_laratimecode_replay')) {
            try {
                return $next($request);
            } catch (Throwable $exception) {
                // The HTTP kernel normally renders exceptions into responses. Keeping the
                // original throwable on the request lets the console replayer compare it.
                $request->attributes->set('_laratimecode_exception', $exception);

                throw $exception;
            }
        }

        if (! (bool) $this->config->get('laratimecode.enabled', false)) {
            return $next($request);
        }

        $this->context->start();

        try {
            $response = $next($request);
            $rendered = $response->exception ?? null;

            if ($rendered instanceof Throwable) {
                $this->recorder->captureIfNeeded($request, $rendered);
            }

            return $response;
        } catch (Throwable $exception) {
            $this->recorder->captureIfNeeded($request, $exception);

            throw $exception;
        } finally {
            $this->context->stop();
        }
    }
}
