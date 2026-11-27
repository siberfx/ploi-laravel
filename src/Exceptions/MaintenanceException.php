<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Ploi\Exceptions\Http\PerformingMaintenance;

/**
 * Ploi is performing maintenance (503).
 */
class MaintenanceException extends PerformingMaintenance implements PloiException
{
    use InteractsWithResponse;
}
