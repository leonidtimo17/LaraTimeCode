<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use LaraTimeCode\LaraTimeCodeServiceProvider;
use LaraTimeCode\Tests\Fixtures\ResponsableFailure;
use LaraTimeCode\Tests\Fixtures\SelfRenderingFailure;
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

        Route::get('/timecode-self-rendering-test', static function (): never {
            throw new SelfRenderingFailure('The self rendering flow exploded.');
        })->name('timecode.self-rendering-test');

        Route::get('/timecode-responsable-test', static function (): never {
            throw new ResponsableFailure('The responsable flow exploded.');
        })->name('timecode.responsable-test');

        Route::get('/timecode-not-found-test', static function (): never {
            throw (new ModelNotFoundException)->setModel('App\Models\User', [999999]);
        })->name('timecode.not-found-test');

        Route::get('/timecode-auth-test', static function (): never {
            throw new RuntimeException('Authenticated as '.(string) (auth()->id() ?? 'guest'));
        })->name('timecode.auth-test');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/laratimecode'));
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/generated-tests'));

        parent::tearDown();
    }
}
