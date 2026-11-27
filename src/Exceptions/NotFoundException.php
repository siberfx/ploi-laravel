<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Ploi\Exceptions\Http\NotFound;

/**
 * The requested Ploi resource does not exist (404).
 */
class NotFoundException extends NotFound implements PloiException
{
    use InteractsWithResponse;
}
