<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

final class SelfRenderingFailure extends RuntimeException
{
    public function render(Request $request): Response
    {
        return new Response('self rendered', 503);
    }
}
