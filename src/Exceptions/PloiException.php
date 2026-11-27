<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Throwable;

/**
 * Every exception thrown by this package implements this interface,
 * so `catch (PloiException $e)` handles all of them.
 */
interface PloiException extends Throwable {}
