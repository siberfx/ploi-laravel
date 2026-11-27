<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Ploi\Ploi;

class PloiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ploi.php', 'ploi');

        $this->app->singleton(PloiManager::class);
        $this->app->alias(PloiManager::class, 'ploi');

        // Type-hint the SDK class or PloiClient to get the default connection injected.
        $this->app->bind(PloiClient::class, fn (Application $app) => $app->make(PloiManager::class)->connection());
        $this->app->bind(Ploi::class, PloiClient::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/ploi.php' => $this->app->configPath('ploi.php'),
        ], 'ploi-config');

        $this->commands([
            Console\CheckCommand::class,
            Console\ServersCommand::class,
            Console\SitesCommand::class,
            Console\DeployCommand::class,
        ]);
    }
}
