<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Ploi\Exceptions\Http\TooManyAttempts;

/**
 * The Ploi API rate limit was hit (429).
 */
class TooManyAttemptsException extends TooManyAttempts implements PloiException
{
    use InteractsWithResponse;

    /** Seconds to wait before retrying, from the Retry-After header, if Ploi sent one. */
    public function retryAfter(): ?int
    {
        $value = $this->response->header('Retry-After');

        return is_numeric($value) ? (int) $value : null;
    }
}
