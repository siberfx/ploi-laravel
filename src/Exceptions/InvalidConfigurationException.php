<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use InvalidArgumentException;

/**
 * The package configuration is incomplete, e.g. a missing token or an unknown connection name.
 */
class InvalidConfigurationException extends InvalidArgumentException implements PloiException
{
    public static function unknownConnection(string $name): self
    {
        return new self("Ploi connection [{$name}] is not defined in config/ploi.php.");
    }

    public static function missingToken(string $name): self
    {
        return new self("Ploi connection [{$name}] has no API token. Set PLOI_API_TOKEN or the connection's 'token' in config/ploi.php.");
    }
}
