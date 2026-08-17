<?php

declare(strict_types=1);

namespace LaraTimeCode\Replay;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

final class RethrowingExceptionHandler implements ExceptionHandler
{
    public function __construct(private readonly ExceptionHandler $original) {}

    public function report(Throwable $e): void
    {
        // A replay is an intentional diagnostic run and should not be reported again.
    }

    public function shouldReport(Throwable $e): bool
    {
        return false;
    }

    public function render($request, Throwable $e): never
    {
        throw $e;
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->original->renderForConsole($output, $e);
    }
}
