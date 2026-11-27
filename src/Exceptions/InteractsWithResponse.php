<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Gives an exception access to the failed Ploi API response.
 */
trait InteractsWithResponse
{
    protected Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;

        parent::__construct(self::buildMessage($response));
    }

    /** The underlying Laravel HTTP response. */
    public function response(): Response
    {
        return $this->response;
    }

    /** The HTTP status code Ploi returned. */
    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * The decoded response body, or one key from it (dot notation).
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        return $this->response->json($key, $default);
    }

    private static function buildMessage(Response $response): string
    {
        $message = $response->json('message');

        $message = is_string($message) && $message !== ''
            ? $message
            : trim(mb_substr($response->body(), 0, 300));

        return sprintf(
            'Ploi API request failed with status %d%s',
            $response->status(),
            $message !== '' ? ': '.$message : '.',
        );
    }
}
