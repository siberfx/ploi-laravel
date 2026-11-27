<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Ploi\Http\Response;
use Ploi\Ploi;
use Siberfx\PloiLaravel\Exceptions\ConnectionFailedException;
use Siberfx\PloiLaravel\Exceptions\ExceptionMapper;
use Siberfx\PloiLaravel\Http\PloiResponse;
use Throwable;

/**
 * The Ploi SDK client, sending its requests through Laravel's HTTP client.
 *
 * Every SDK resource (servers, sites, databases, deployments, ...) works
 * unchanged, but requests now honour Http::fake(), Laravel's HTTP events,
 * the configured timeouts and retries, and return a PloiResponse.
 *
 * @phpstan-type ClientOptions array{
 *     base_url?: string,
 *     timeout?: int,
 *     connect_timeout?: int,
 *     retry?: array{times?: int, sleep?: int},
 *     cache?: array{store?: string|null, prefix?: string, ttl?: int},
 * }
 */
class PloiClient extends Ploi
{
    /** Hard stop for lazy() so a misbehaving API can never loop forever. */
    public const MAX_PAGES = 1000;

    private string $token;

    /**
     * @param  ClientOptions  $options
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
        string $token,
        private readonly array $options = [],
        private readonly string $name = 'default',
    ) {
        // The parent constructor builds its own Guzzle client, which this class never uses.
        $this->token = $token;
    }

    /** The connection name this client was created for. */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @param  string  $token
     */
    public function setApiToken($token): self
    {
        $this->token = (string) $token;

        return $this;
    }

    public function getApiToken(): string
    {
        return $this->token;
    }

    /**
     * Send an API call. Every SDK resource method ends up here.
     *
     * @param  array<string, mixed>  $options  Guzzle-style options as the SDK passes them ('body', 'json', 'query')
     *
     * @throws Exceptions\PloiException
     */
    public function makeAPICall(string $url, string $method = 'get', array $options = []): Response
    {
        $method = strtolower($method);

        if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
            throw new InvalidArgumentException("Invalid method type [{$method}].");
        }

        $request = $this->request();

        if (isset($options['query']) && is_array($options['query'])) {
            $request = $request->withQueryParameters($options['query']);
        }

        if (isset($options['json']) && is_array($options['json'])) {
            $request = $request->withBody((string) json_encode($options['json']), 'application/json');
        } elseif (isset($options['body']) && is_string($options['body'])) {
            $request = $request->withBody($options['body'], 'application/json');
        }

        try {
            $response = $request->send(strtoupper($method), ltrim($url, '/'));
        } catch (ConnectionException $e) {
            throw ConnectionFailedException::from($e);
        }

        if ($response->failed()) {
            throw ExceptionMapper::fromResponse($response);
        }

        return new PloiResponse($response);
    }

    /**
     * A pre-configured Laravel HTTP request for endpoints the SDK does not cover yet.
     *
     * Ploi::request()->get('servers/1/sites')->json();
     */
    public function request(): PendingRequest
    {
        $retry = $this->options['retry'] ?? [];

        return $this->http
            ->baseUrl(rtrim($this->options['base_url'] ?? 'https://ploi.io/api', '/'))
            ->withToken($this->token)
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout($this->options['timeout'] ?? 30)
            ->connectTimeout($this->options['connect_timeout'] ?? 10)
            ->retry(
                max(0, (int) ($retry['times'] ?? 2)) + 1,
                max(0, (int) ($retry['sleep'] ?? 500)),
                fn (Throwable $e): bool => self::shouldRetry($e),
                throw: false,
            );
    }

    /**
     * Iterate every item of a paginated list, fetching pages lazily.
     *
     * Ploi::lazy(fn (int $page) => Ploi::server()->page($page, 50))
     *     ->each(fn (array $server) => ...);
     *
     * @param  Closure(int, PloiClient): Response  $page
     * @return LazyCollection<int, mixed>
     */
    public function lazy(Closure $page): LazyCollection
    {
        return LazyCollection::make(function () use ($page) {
            $number = 1;

            do {
                $response = $page($number, $this);

                if (! $response instanceof PloiResponse) {
                    throw new InvalidArgumentException('The lazy() callback must return a response from this client, e.g. fn (int $page) => Ploi::server()->page($page).');
                }

                yield from $response->collect()->values()->all();

                $number++;
            } while ($response->hasMorePages() && $number <= self::MAX_PAGES);
        })->values();
    }

    /**
     * Cache the result of a Ploi call. Responses are stored as plain arrays,
     * which keeps the cache safe with Laravel 13's serializable_classes => false.
     *
     * Ploi::remember('servers', fn (PloiClient $ploi) => $ploi->server()->get());
     *
     * @template TValue
     *
     * @param  Closure(PloiClient): TValue  $callback
     * @return ($callback is Closure(PloiClient): Response ? array<array-key, mixed>|null : TValue)
     */
    public function remember(string $key, Closure $callback, DateTimeInterface|DateInterval|int|null $ttl = null): mixed
    {
        return $this->cacheStore()->remember(
            $this->cacheKey($key),
            $ttl ?? ($this->options['cache']['ttl'] ?? 300),
            function () use ($callback) {
                $value = $callback($this);

                return $value instanceof Response ? self::toArray($value) : $value;
            },
        );
    }

    /** Remove a value stored with remember(). */
    public function forget(string $key): bool
    {
        return $this->cacheStore()->forget($this->cacheKey($key));
    }

    /**
     * Trigger a deployment of a site.
     *
     * @throws Exceptions\PloiException
     */
    public function deploy(int $server, int $site): Response
    {
        return $this->server($server)->sites($site)->deployment()->deploy();
    }

    private function cacheStore(): CacheRepository
    {
        return $this->cache->store($this->options['cache']['store'] ?? null);
    }

    private function cacheKey(string $key): string
    {
        return ($this->options['cache']['prefix'] ?? 'ploi').":{$this->name}:{$key}";
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function toArray(Response $response): ?array
    {
        if ($response instanceof PloiResponse) {
            $json = $response->json();

            return is_array($json) ? $json : null;
        }

        $json = json_decode((string) json_encode($response->getJson()), true);

        return is_array($json) ? $json : null;
    }

    private static function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof RequestException
            && ($e->response->status() === 429 || $e->response->serverError());
    }
}
