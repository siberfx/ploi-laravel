<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Ploi could not be reached (DNS, TLS or timeout), even after retries.
 */
class ConnectionFailedException extends RuntimeException implements PloiException
{
    public static function from(ConnectionException $e): self
    {
        return new self('Could not connect to the Ploi API: '.$e->getMessage(), 0, $e);
    }
}
