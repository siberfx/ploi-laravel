<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Turns a failed Ploi response into the matching exception.
 *
 * Each exception extends the SDK's own exception class, so code that
 * catches \Ploi\Exceptions\Http\NotFound (and friends) keeps working.
 */
final class ExceptionMapper
{
    public static function fromResponse(Response $response): PloiException
    {
        return match (true) {
            $response->status() === 401 => new UnauthenticatedException($response),
            $response->status() === 404 => new NotFoundException($response),
            $response->status() === 405 => new NotAllowedException($response),
            $response->status() === 422 => new ValidationException($response),
            $response->status() === 429 => new TooManyAttemptsException($response),
            $response->status() === 503 => new MaintenanceException($response),
            $response->serverError() => new ServerErrorException($response),
            default => new RequestFailedException($response),
        };
    }
}
