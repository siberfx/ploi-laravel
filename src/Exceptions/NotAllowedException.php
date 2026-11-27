<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Ploi\Exceptions\Http\NotAllowed;

/**
 * The HTTP method is not allowed for this endpoint (405).
 */
class NotAllowedException extends NotAllowed implements PloiException
{
    use InteractsWithResponse;
}
