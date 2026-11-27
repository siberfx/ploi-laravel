<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Siberfx\PloiLaravel\Exceptions\InvalidConfigurationException;

/**
 * Resolves Ploi clients per configured account (connection).
 *
 * Calls the manager does not handle itself go to the default connection,
 * so Ploi::server()->get() and Ploi::connection('main')->server()->get()
 * are the same.
 *
 * @mixin PloiClient
 */
class PloiManager
{
    /** @var array<string, PloiClient> */
    private array $clients = [];

    public function __construct(
        private readonly Config $config,
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
    ) {}

    /**
     * The client for a configured connection (the default one when null).
     *
     * @throws InvalidConfigurationException
     */
    public function connection(?string $name = null): PloiClient
    {
        $name ??= $this->getDefaultConnection();

        return $this->clients[$name] ??= $this->resolve($name);
    }

    /**
     * A client for a token that is not in the config, e.g. one stored per tenant.
     *
     * Ploi::withToken($team->ploi_token)->server()->get();
     *
     * @param  array<string, mixed>  $options  overrides for the HTTP and cache options
     */
    public function withToken(string $token, array $options = []): PloiClient
    {
        if ($token === '') {
            throw new InvalidConfigurationException('The Ploi API token must not be empty.');
        }

        // Cache keys are scoped per token, so tenants never read each other's cached data.
        $name = 'token-'.substr(hash('sha256', $token), 0, 16);

        return $this->makeClient($token, array_replace_recursive($this->sharedOptions(), $options), $name);
    }

    public function getDefaultConnection(): string
    {
        return (string) $this->config->get('ploi.default', 'main');
    }

    public function setDefaultConnection(string $name): void
    {
        $this->config->set('ploi.default', $name);
    }

    /**
     * Forget resolved clients so the next call rebuilds them from config.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];

            return;
        }

        unset($this->clients[$name]);
    }

    /**
     * Fake Ploi API responses (wraps Http::fake()).
     *
     * Keys are endpoint paths relative to the API, values are arrays (sent
     * as a 200 JSON response) or Http::response() / closures. Anything not
     * listed returns an empty 200 list.
     *
     * Ploi::fake(['servers' => ['data' => [['id' => 1, 'name' => 'web-1']]]]);
     *
     * @param  array<string, mixed>  $responses
     */
    public function fake(array $responses = []): HttpFactory
    {
        $base = rtrim((string) $this->config->get('ploi.base_url', 'https://ploi.io/api'), '/');
        $stubs = [];

        foreach ($responses as $path => $response) {
            $url = str_contains($path, '://') ? $path : $base.'/'.ltrim($path, '/');

            $stubs[$url] = is_array($response) ? HttpFactory::response($response) : $response;
        }

        $stubs[$base.'/*'] ??= HttpFactory::response(['data' => []]);

        return $this->http->fake($stubs);
    }

    /**
     * Build a fake JSON response for use in Ploi::fake().
     *
     * @param  array<array-key, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public static function response(array $body = [], int $status = 200, array $headers = []): PromiseInterface
    {
        return HttpFactory::response($body, $status, $headers);
    }

    /**
     * Assert a request to the Ploi API was sent (wraps Http::assertSent()).
     *
     * @param  callable(Request, Response|null): bool  $callback
     */
    public function assertSent(callable $callback): void
    {
        $this->http->assertSent($callback);
    }

    public function assertNothingSent(): void
    {
        $this->http->assertNothingSent();
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }

    private function resolve(string $name): PloiClient
    {
        $connection = $this->config->get("ploi.connections.{$name}");

        if (! is_array($connection)) {
            throw InvalidConfigurationException::unknownConnection($name);
        }

        $token = $connection['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw InvalidConfigurationException::missingToken($name);
        }

        unset($connection['token']);

        return $this->makeClient($token, array_replace_recursive($this->sharedOptions(), $connection), $name);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function makeClient(string $token, array $options, string $name): PloiClient
    {
        return new PloiClient($this->http, $this->cache, $token, $options, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedOptions(): array
    {
        $config = $this->config->get('ploi', []);

        return array_intersect_key(
            is_array($config) ? $config : [],
            array_flip(['base_url', 'timeout', 'connect_timeout', 'retry', 'cache']),
        );
    }
}
