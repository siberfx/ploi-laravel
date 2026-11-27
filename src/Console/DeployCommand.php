<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Console;

use Siberfx\PloiLaravel\PloiClient;

class DeployCommand extends PloiCommand
{
    protected $signature = 'ploi:deploy
        {server : The Ploi server ID (see ploi:servers)}
        {site : The Ploi site ID (see ploi:sites)}
        {--to-production : Deploy a staging site to its production site}
        {--connection= : The Ploi connection from config/ploi.php}
        {--force : Skip the confirmation when the app runs in production}';

    protected $description = 'Trigger a deployment of a Ploi site';

    protected function handlePloi(PloiClient $ploi): int
    {
        $server = $this->intArgument('server');
        $site = $this->intArgument('site');

        if (! $this->confirmedForProduction($server, $site)) {
            return self::FAILURE;
        }

        $deployment = $ploi->server($server)->sites($site)->deployment();

        $this->option('to-production') ? $deployment->deployToProduction() : $deployment->deploy();

        $this->components->info(sprintf(
            'Deployment of site %d on server %d started%s. Follow it in the Ploi dashboard.',
            $site,
            $server,
            $this->option('to-production') ? ' (to production)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * From an app running in production, ask first unless --force is given.
     * Without a terminal to ask in (CI, cron) it refuses rather than guessing.
     */
    private function confirmedForProduction(int $server, int $site): bool
    {
        if ($this->option('force') || ! $this->laravel->environment('production')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('This app runs in production. Pass --force to deploy without confirmation.');

            return false;
        }

        if ($this->components->confirm("Deploy site {$site} on server {$server}?")) {
            return true;
        }

        $this->components->warn('Deployment cancelled.');

        return false;
    }
}
