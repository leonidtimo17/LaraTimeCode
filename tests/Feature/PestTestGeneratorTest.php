<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Feature;

use LaraTimeCode\Replay\PestTestGenerator;
use LaraTimeCode\Tests\TestCase;

final class PestTestGeneratorTest extends TestCase
{
    public function test_it_generates_a_pest_regression_test(): void
    {
        $path = $this->app->make(PestTestGenerator::class)->generate([
            'id' => '20260817-120000-123456-abcd1234',
            'captured_at' => '2026-08-17T12:00:00+09:00',
            'request' => [
                'method' => 'POST',
                'uri' => '/checkout',
                'query' => [],
                'input' => ['order_id' => 42],
                'headers' => ['content-type' => 'application/json'],
            ],
            'auth' => null,
        ]);

        self::assertFileExists($path);
        self::assertStringContainsString("->json('POST', '/checkout'", (string) file_get_contents($path));
        self::assertStringContainsString('assertSuccessful()', (string) file_get_contents($path));
    }
}
