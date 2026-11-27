<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Ploi\Exceptions\Http\InternalServerError;

/**
 * Ploi returned a server error (5xx other than 503).
 */
class ServerErrorException extends InternalServerError implements PloiException
{
    use InteractsWithResponse;
}
