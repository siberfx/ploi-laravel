<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

/**
 * Ploi returned an error status with no more specific exception (e.g. 400 or 403).
 */
class RequestFailedException extends \RuntimeException implements PloiException
{
    use InteractsWithResponse;
}
