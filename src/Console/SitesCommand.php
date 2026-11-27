<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Console;

use Siberfx\PloiLaravel\PloiClient;

class SitesCommand extends PloiCommand
{
    protected $signature = 'ploi:sites
        {server : The Ploi server ID (see ploi:servers)}
        {--connection= : The Ploi connection from config/ploi.php}
        {--json : Print the full site data as JSON}';

    protected $description = 'List the sites on a Ploi server';

    protected function handlePloi(PloiClient $ploi): int
    {
        $server = $this->intArgument('server');

        $this->renderList(
            ['id', 'domain', 'status', 'has_repository'],
            $ploi->lazy(fn (int $page) => $ploi->server($server)->sites()->page($page, 50)),
        );

        return self::SUCCESS;
    }
}
