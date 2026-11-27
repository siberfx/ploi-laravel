<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Ploi\Exceptions\Http\Unauthenticated;

/**
 * Ploi rejected the API token (401).
 */
class UnauthenticatedException extends Unauthenticated implements PloiException
{
    use InteractsWithResponse;
}
