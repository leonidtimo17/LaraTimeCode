<?php

declare(strict_types=1);

namespace LaraTimeCode\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use LaraTimeCode\Capture\CaptureContext;
use LaraTimeCode\Capture\FailureRecorder;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CaptureFailures
{
    public function __construct(
        private readonly FailureRecorder $recorder,
        private readonly CaptureContext $context,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
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
            return $next($request);
        } catch (Throwable $exception) {
            if ($this->recorder->shouldCapture($request, $exception)) {
                try {
                    $id = $this->recorder->capture($request, $exception);
                    $request->attributes->set('_laratimecode_id', $id);
                } catch (Throwable $captureException) {
                    $this->logger->warning('LaraTimeCode could not capture a failed request.', [
                        'exception' => $captureException,
                    ]);
                }
            }

            throw $exception;
        } finally {
            $this->context->stop();
        }
    }
}
