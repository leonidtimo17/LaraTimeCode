<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Unit;

use LaraTimeCode\Redaction\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
    public function test_it_redacts_nested_sensitive_fields(): void
    {
        $redactor = new Redactor(['password', '*.token', 'authorization']);

        $result = $redactor->redact([
            'email' => 'person@example.com',
            'password' => 'secret',
            'oauth' => ['token' => 'abc123'],
            'headers' => ['authorization' => 'Bearer secret'],
        ]);

        self::assertSame('person@example.com', $result['email']);
        self::assertSame('[REDACTED]', $result['password']);
        self::assertSame('[REDACTED]', $result['oauth']['token']);
        self::assertSame('[REDACTED]', $result['headers']['authorization']);
    }

    public function test_it_truncates_multibyte_strings_without_breaking_encoding(): void
    {
        $redactor = new Redactor([], '[REDACTED]', 9);
        $result = $redactor->redact(str_repeat('привет', 4));

        self::assertSame("прив\n[TRUNCATED 40 BYTES]", $result);
        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
        self::assertIsString(json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_it_redacts_query_parameters_in_urls(): void
    {
        $redactor = new Redactor(['token']);

        self::assertSame(
            'https://example.com/callback?token=%5BREDACTED%5D&page=2',
            $redactor->redactUrl('https://example.com/callback?token=secret&page=2'),
        );
    }
}
