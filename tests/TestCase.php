<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\PloiLaravel\PloiServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [PloiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('ploi.connections.main.token', 'test-token');
        $app['config']->set('ploi.connections.agency', ['token' => 'agency-token']);
        $app['config']->set('ploi.retry.sleep', 0);
    }
}
