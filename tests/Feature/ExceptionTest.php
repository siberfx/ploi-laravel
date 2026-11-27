<?php

declare(strict_types=1);

use Ploi\Exceptions\Http\InternalServerError;
use Ploi\Exceptions\Http\NotAllowed;
use Ploi\Exceptions\Http\NotFound;
use Ploi\Exceptions\Http\NotValid;
use Ploi\Exceptions\Http\PerformingMaintenance;
use Ploi\Exceptions\Http\TooManyAttempts;
use Ploi\Exceptions\Http\Unauthenticated;
use Siberfx\PloiLaravel\Exceptions\MaintenanceException;
use Siberfx\PloiLaravel\Exceptions\NotAllowedException;
use Siberfx\PloiLaravel\Exceptions\NotFoundException;
use Siberfx\PloiLaravel\Exceptions\PloiException;
use Siberfx\PloiLaravel\Exceptions\RequestFailedException;
use Siberfx\PloiLaravel\Exceptions\ServerErrorException;
use Siberfx\PloiLaravel\Exceptions\TooManyAttemptsException;
use Siberfx\PloiLaravel\Exceptions\UnauthenticatedException;
use Siberfx\PloiLaravel\Exceptions\ValidationException;
use Siberfx\PloiLaravel\Facades\Ploi;

function failWith(int $status, array $body = [], array $headers = []): Throwable
{
    config()->set('ploi.retry.times', 0);
    Ploi::fake(['servers/1' => Ploi::response($body, $status, $headers)]);

    try {
        Ploi::server(1)->get();
    } catch (Throwable $e) {
        return $e;
    }

    throw new RuntimeException('Expected an exception.');
}

it('maps each status to an exception that still extends the SDK one', function (int $status, string $class, ?string $sdkClass) {
    $e = failWith($status, ['message' => 'Nope']);

    expect($e)->toBeInstanceOf($class)
        ->toBeInstanceOf(PloiException::class)
        ->and($e->status())->toBe($status)
        ->and($e->getMessage())->toBe("Ploi API request failed with status {$status}: Nope");

    if ($sdkClass) {
        expect($e)->toBeInstanceOf($sdkClass);
    }
})->with([
    [401, UnauthenticatedException::class, Unauthenticated::class],
    [403, RequestFailedException::class, null],
    [404, NotFoundException::class, NotFound::class],
    [405, NotAllowedException::class, NotAllowed::class],
    [422, ValidationException::class, NotValid::class],
    [429, TooManyAttemptsException::class, TooManyAttempts::class],
    [500, ServerErrorException::class, InternalServerError::class],
    [502, ServerErrorException::class, InternalServerError::class],
    [503, MaintenanceException::class, PerformingMaintenance::class],
]);

it('exposes validation errors', function () {
    $e = failWith(422, ['message' => 'Invalid', 'errors' => ['domain' => ['The domain is taken.']]]);

    expect($e->errors())->toBe(['domain' => ['The domain is taken.']])
        ->and($e->json('errors.domain.0'))->toBe('The domain is taken.');
});

it('exposes the retry-after header on rate limits', function () {
    $e = failWith(429, [], ['Retry-After' => '30']);

    expect($e->retryAfter())->toBe(30);
});

it('falls back to the body when Ploi sends no message', function () {
    $e = failWith(400, ['error' => 'bad']);

    expect($e->getMessage())->toBe('Ploi API request failed with status 400: {"error":"bad"}');
});
