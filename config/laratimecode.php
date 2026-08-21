<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [
    /*
    | LaraTimeCode never captures requests until this switch is explicitly enabled.
    */
    'enabled' => (bool) env('LARATIMECODE_ENABLED', false),
    'release' => env('APP_RELEASE'),

    'storage' => [
        'path' => env('LARATIMECODE_PATH', storage_path('laratimecode')),
        'encrypt' => (bool) env('LARATIMECODE_ENCRYPT', true),
        'retention_days' => (int) env('LARATIMECODE_RETENTION_DAYS', 14),
        'max_files' => (int) env('LARATIMECODE_MAX_FILES', 100),
    ],

    'capture' => [
        'sample_rate' => (float) env('LARATIMECODE_SAMPLE_RATE', 1.0),
        'include_trace' => true,
        'trace_frames' => 40,
        'include_ip' => false,
        'auth_guard' => 'web',
        'max_string_length' => 16_384,
        'max_queries' => 100,
        'query_bindings' => false,
        'outbound_http' => true,
        'outbound_response_body' => false,
        'outbound_response_bytes' => 8_192,

        'headers' => [
            'accept',
            'content-type',
            'user-agent',
            'x-request-id',
            'x-correlation-id',
        ],

        'exclude_paths' => [
            '_debugbar/*',
            'horizon/*',
            'pulse/*',
            'telescope/*',
            'up',
        ],

        'ignore_exceptions' => [
            AuthenticationException::class,
            AuthorizationException::class,
            NotFoundHttpException::class,
            RecordsNotFoundException::class,
            TokenMismatchException::class,
            ValidationException::class,
        ],
    ],

    'redaction' => [
        'replacement' => '[REDACTED]',
        'fields' => [
            'authorization',
            'cookie',
            'password',
            'password_confirmation',
            'secret',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'client_secret',
            'credit_card',
            'card_number',
            'cvv',
            '*.password',
            '*.secret',
            '*.token',
        ],
    ],

    'replay' => [
        'restore_auth_user' => false,
    ],

    'tests_path' => base_path('tests/Feature/LaraTimeCode'),
];
