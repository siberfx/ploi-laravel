<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Console;

use Siberfx\PloiLaravel\PloiClient;

class ServersCommand extends PloiCommand
{
    protected $signature = 'ploi:servers
        {--connection= : The Ploi connection from config/ploi.php}
        {--json : Print the full server data as JSON}';

    protected $description = 'List the servers on your Ploi account';

    protected function handlePloi(PloiClient $ploi): int
    {
        $this->renderList(
            ['id', 'name', 'ip_address', 'status'],
            $ploi->lazy(fn (int $page) => $ploi->server()->page($page, 50)),
        );

        return self::SUCCESS;
    }
}
