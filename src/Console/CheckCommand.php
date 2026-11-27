<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Console;

use Siberfx\PloiLaravel\Http\PloiResponse;
use Siberfx\PloiLaravel\PloiClient;

class CheckCommand extends PloiCommand
{
    protected $signature = 'ploi:check {--connection= : The Ploi connection from config/ploi.php}';

    protected $description = 'Check that the Ploi API token works';

    protected function handlePloi(PloiClient $ploi): int
    {
        $response = $ploi->user()->get();

        $who = $response instanceof PloiResponse
            ? array_filter([$response->data('name'), $response->data('email')], 'is_string')
            : [];

        $this->components->info(sprintf(
            'Connected to Ploi on connection [%s]%s.',
            $ploi->name(),
            $who === [] ? '' : ' as '.implode(' · ', $who),
        ));

        return self::SUCCESS;
    }
}
