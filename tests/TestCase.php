<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use LaraTimeCode\LaraTimeCodeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaraTimeCodeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('laratimecode.enabled', true);
        $app['config']->set('laratimecode.storage.encrypt', false);
        $app['config']->set('laratimecode.storage.path', storage_path('framework/testing/laratimecode'));
        $app['config']->set('laratimecode.capture.ignore_exceptions', []);
        $app['config']->set('laratimecode.tests_path', storage_path('framework/testing/generated-tests'));
    }

    protected function defineRoutes($router): void
    {
        Route::post('/timecode-test', static function (): never {
            throw new RuntimeException('The checkout exploded.');
        })->name('timecode.test');

        Route::get('/timecode-http-test', static function (): never {
            Http::get('https://api.example.test/orders?token=secret');

            throw new RuntimeException('The integration exploded.');
        })->name('timecode.http-test');

        Route::get('/timecode-db-test', static function (): never {
            DB::select('select 1 as one');

            throw new RuntimeException('The query flow exploded.');
        })->name('timecode.db-test');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/laratimecode'));
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/generated-tests'));

        parent::tearDown();
    }
}
