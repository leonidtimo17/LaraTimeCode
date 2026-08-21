<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Fixtures;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use RuntimeException;

final class ResponsableFailure extends RuntimeException implements Responsable
{
    public function toResponse($request): Response
    {
        return new Response('responsable', 502);
    }
}
