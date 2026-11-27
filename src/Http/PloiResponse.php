<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Http;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Collection;
use Ploi\Http\Response;

/**
 * A Ploi SDK response with Laravel conveniences.
 *
 * It still is a \Ploi\Http\Response, so getData() and getJson() behave
 * exactly like the plain SDK. The added methods return arrays and
 * collections instead of stdClass objects.
 */
class PloiResponse extends Response
{
    public function __construct(protected HttpResponse $http)
    {
        parent::__construct($http->toPsrResponse());
    }

    /** The underlying Laravel HTTP response (headers, status, body). */
    public function http(): HttpResponse
    {
        return $this->http;
    }

    public function status(): int
    {
        return $this->http->status();
    }

    /**
     * The decoded body as arrays, or one key from it (dot notation).
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        return $this->http->json($key, $default);
    }

    /**
     * The "data" key when Ploi wraps the payload in one, otherwise the whole body, as arrays.
     */
    public function data(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->hasDataKey() ? $this->json('data') : $this->json();

        return $key === null ? ($data ?? $default) : data_get($data, $key, $default);
    }

    /**
     * The payload as a collection: list endpoints give one item per resource.
     *
     * @return Collection<array-key, mixed>
     */
    public function collect(?string $key = null): Collection
    {
        $value = $this->data($key);

        return new Collection(is_array($value) ? $value : ($value === null ? [] : [$value]));
    }

    /**
     * Pagination metadata (current_page, last_page, per_page, total, ...), or one key from it.
     */
    public function meta(?string $key = null, mixed $default = null): mixed
    {
        return $this->json($key === null ? 'meta' : "meta.{$key}", $default);
    }

    public function currentPage(): ?int
    {
        $page = $this->meta('current_page');

        return is_numeric($page) ? (int) $page : null;
    }

    public function lastPage(): ?int
    {
        $page = $this->meta('last_page');

        return is_numeric($page) ? (int) $page : null;
    }

    /** Whether another page follows this one. */
    public function hasMorePages(): bool
    {
        if ($this->json('links.next')) {
            return true;
        }

        $current = $this->currentPage();
        $last = $this->lastPage();

        return $current !== null && $last !== null && $current < $last;
    }

    private function hasDataKey(): bool
    {
        $json = $this->json();

        return is_array($json) && array_key_exists('data', $json);
    }
}
