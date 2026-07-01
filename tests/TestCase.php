<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Tests;

use Nizaamomer\LaravelFib\FibServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FibServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('fib.accounts.default', [
            'base_url' => 'https://fib.stage.fib.iq',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'grant_type' => 'client_credentials',
        ]);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
    }
}
