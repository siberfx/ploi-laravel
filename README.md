# Ploi for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/siberfx/ploi-laravel.svg?style=flat-square)](https://packagist.org/packages/siberfx/ploi-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/siberfx/ploi-laravel.svg?style=flat-square)](https://packagist.org/packages/siberfx/ploi-laravel)
[![Tests](https://img.shields.io/github/actions/workflow/status/siberfx/ploi-laravel/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/siberfx/ploi-laravel/actions/workflows/tests.yml)
[![Code Quality](https://img.shields.io/github/actions/workflow/status/siberfx/ploi-laravel/code-quality.yml?branch=main&label=code%20quality&style=flat-square)](https://github.com/siberfx/ploi-laravel/actions/workflows/code-quality.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](phpstan.neon.dist)
[![PHP Version](https://img.shields.io/packagist/dependency-v/siberfx/ploi-laravel/php?style=flat-square)](https://packagist.org/packages/siberfx/ploi-laravel)
[![Laravel](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x-FF2D20.svg?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/github/license/siberfx/ploi-laravel.svg?style=flat-square)](LICENSE)

The official [Ploi PHP SDK](https://github.com/ploi/ploi-php-sdk), made for Laravel.

Every resource the SDK has (servers, sites, deployments, databases, cronjobs, daemons, certificates, backups, status pages and the rest) works exactly as documented upstream. This package changes how the calls are made and what you get back:

- **Laravel's HTTP client under the hood.** `Http::fake()`, Laravel's HTTP events, timeouts and automatic retries apply to every SDK call.
- **Config, facade and dependency injection.** Set `PLOI_API_TOKEN` and call `Ploi::server()->get()`, or type-hint `Ploi\Ploi`.
- **Several Ploi accounts.** Named connections in config, plus `Ploi::withToken()` for tokens you store per tenant.
- **Arrays and collections.** `$response->collect()`, `$response->data('name')`, and lazy pagination over every page.
- **Typed exceptions.** `NotFoundException`, `ValidationException::errors()`, `TooManyAttemptsException::retryAfter()`; errors the SDK let through silently (such as 403) now throw.
- **Caching** that stores plain arrays (safe with Laravel 13's `serializable_classes => false`).
- **Test helpers:** `Ploi::fake()` and `Ploi::assertSent()`.
- **Artisan commands:** `ploi:check`, `ploi:servers`, `ploi:sites` and `ploi:deploy`.

## Requirements

| Package | PHP | Laravel |
| ------- | --- | ------- |
| 1.x | 8.3, 8.4, 8.5 | 12.x, 13.x |

## Installation

```bash
composer require siberfx/ploi-laravel
```

Add your API token (create one at **Ploi → Profile → API keys**) to `.env`:

```dotenv
PLOI_API_TOKEN=your-token
```

Check that it works:

```bash
php artisan ploi:check
```

Publishing the config is optional; do it only to add connections or change the defaults:

```bash
php artisan vendor:publish --tag=ploi-config
```

## Usage

### The SDK, through the facade

Everything from the [SDK documentation](https://github.com/ploi/ploi-php-sdk) works on the facade:

```php
use Siberfx\PloiLaravel\Facades\Ploi;

$servers = Ploi::server()->get();                    // first page of servers
$server  = Ploi::server(123)->get();                 // one server
$sites   = Ploi::server(123)->sites()->get();        // its sites

Ploi::server(123)->sites(456)->deployment()->deploy();
Ploi::server(123)->databases()->create('shop', 'shop_user', 'secret');
Ploi::server(123)->cronjobs()->create('php artisan schedule:run', '* * * * *');
Ploi::server(123)->restart();
```

Or inject it. Both the SDK class and the package client resolve to the default connection:

```php
use Ploi\Ploi;

class DeploySite
{
    public function __construct(private Ploi $ploi) {}

    public function __invoke(int $server, int $site): void
    {
        $this->ploi->server($server)->sites($site)->deployment()->deploy();
    }
}
```

There is a shortcut for the most common call:

```php
Ploi::deploy(server: 123, site: 456);
```

### Responses

Calls return a `Siberfx\PloiLaravel\Http\PloiResponse`. It still is a `Ploi\Http\Response`, so `getData()` and `getJson()` behave like the plain SDK, and it adds array-based helpers:

```php
$response = Ploi::server(123)->sites()->get();

$response->collect();                 // Collection of sites (the "data" list)
$response->collect()->pluck('domain');
$response->data();                    // the "data" payload as arrays
$response->data('0.domain');          // dot notation into "data"
$response->json('meta.total');        // anything in the body
$response->status();                  // 200
$response->http();                    // the underlying Illuminate\Http\Client\Response

$response->currentPage();             // 1
$response->lastPage();                // 4
$response->hasMorePages();            // true
```

### Every page, lazily

`Ploi::lazy()` walks a paginated list page by page and only fetches the next page when you get there:

```php
Ploi::lazy(fn (int $page) => Ploi::server()->page($page, 50))
    ->filter(fn (array $server) => $server['status'] === 'active')
    ->each(fn (array $server) => logger()->info($server['name']));
```

### Several Ploi accounts

Add connections in `config/ploi.php`. Any HTTP option can be overridden per connection:

```php
'connections' => [
    'main' => [
        'token' => env('PLOI_API_TOKEN'),
    ],
    'agency' => [
        'token' => env('PLOI_AGENCY_TOKEN'),
        'timeout' => 60,
    ],
],
```

```php
Ploi::connection('agency')->server()->get();
```

For tokens that live in your database, for example one per team:

```php
Ploi::withToken($team->ploi_token)->server()->get();
```

### Caching

`remember()` caches the result for the configured TTL (`PLOI_CACHE_TTL`, default 300 seconds). Responses are stored as plain arrays, so the cache never holds PHP objects:

```php
$servers = Ploi::remember('servers', fn ($ploi) => $ploi->server()->get());
// ['data' => [...], 'links' => [...], 'meta' => [...]]

$servers = Ploi::remember('servers', fn ($ploi) => $ploi->server()->get(), now()->addMinutes(15));

Ploi::forget('servers');
```

Keys are scoped by connection (and by token for `withToken()` clients), so accounts never read each other's cache.

### Endpoints the SDK does not cover yet

`Ploi::request()` gives you a Laravel HTTP request that already has the base URL, token, timeouts and retries set:

```php
$response = Ploi::request()->get('servers/123/some-new-endpoint')->json();
```

### Errors

Every exception implements `Siberfx\PloiLaravel\Exceptions\PloiException` and, where one exists, extends the matching SDK exception, so existing `catch (\Ploi\Exceptions\Http\NotFound $e)` blocks keep working.

| Status | Exception | Extra |
| ------ | --------- | ----- |
| 401 | `UnauthenticatedException` | |
| 404 | `NotFoundException` | |
| 405 | `NotAllowedException` | |
| 422 | `ValidationException` | `errors()` |
| 429 | `TooManyAttemptsException` | `retryAfter()` |
| 503 | `MaintenanceException` | |
| other 5xx | `ServerErrorException` | |
| other 4xx (e.g. 403) | `RequestFailedException` | |
| no connection | `ConnectionFailedException` | |
| bad config | `InvalidConfigurationException` | |

Response-based exceptions also have `status()`, `json()` and `response()`:

```php
use Siberfx\PloiLaravel\Exceptions\PloiException;
use Siberfx\PloiLaravel\Exceptions\ValidationException;

try {
    Ploi::server(123)->sites()->create('example.com');
} catch (ValidationException $e) {
    $e->errors();  // ['root_domain' => ['The root domain has already been taken.']]
} catch (PloiException $e) {
    report($e);
}
```

### Retries and timeouts

Connection errors, 429 and 5xx responses are retried (2 retries, 500 ms apart, by default). Other errors fail right away. Configure it with `PLOI_RETRY_TIMES`, `PLOI_RETRY_SLEEP`, `PLOI_TIMEOUT` and `PLOI_CONNECT_TIMEOUT`, or per connection in `config/ploi.php`.

Because requests go through Laravel's HTTP client, Laravel's `RequestSending`, `ResponseReceived` and `ConnectionFailed` events fire for every Ploi call, so tools like Telescope and Pulse see them too.

## Artisan commands

```bash
php artisan ploi:check                      # verify the token
php artisan ploi:servers                    # list servers (every page)
php artisan ploi:servers --json             # full server data as JSON
php artisan ploi:sites 123                  # list the sites of server 123
php artisan ploi:deploy 123 456             # deploy site 456 on server 123
php artisan ploi:deploy 123 456 --to-production   # deploy a staging site to production
```

Every command accepts `--connection=agency`. When your app runs in production, `ploi:deploy` asks before deploying. Without a terminal to ask in (CI, cron, `--no-interaction`) it refuses unless you pass `--force`.

## Testing your code

`Ploi::fake()` wraps `Http::fake()`. Keys are endpoint paths, values are arrays (sent as 200 JSON) or `Ploi::response()`. Anything you don't list returns an empty 200 list:

```php
use Illuminate\Http\Client\Request;
use Siberfx\PloiLaravel\Facades\Ploi;

Ploi::fake([
    'servers/123/sites*' => ['data' => [['id' => 456, 'domain' => 'example.com']]],
    'servers/123/sites/456/deploy' => Ploi::response(['message' => 'Deploying'], 200),
    'servers/999' => Ploi::response(['message' => 'Not found'], 404),
]);

// ... run your code ...

Ploi::assertSent(fn (Request $request) => $request->method() === 'POST'
    && str_ends_with($request->url(), 'servers/123/sites/456/deploy'));
```

## Configuration reference

| Env variable | Default | |
| ------------ | ------- | - |
| `PLOI_API_TOKEN` | | Token of the `main` connection |
| `PLOI_CONNECTION` | `main` | Default connection |
| `PLOI_BASE_URL` | `https://ploi.io/api` | API base URL |
| `PLOI_TIMEOUT` | `30` | Request timeout (seconds) |
| `PLOI_CONNECT_TIMEOUT` | `10` | Connect timeout (seconds) |
| `PLOI_RETRY_TIMES` | `2` | Retries on connection errors, 429 and 5xx |
| `PLOI_RETRY_SLEEP` | `500` | Milliseconds between retries |
| `PLOI_CACHE_STORE` | default store | Cache store for `remember()` |
| `PLOI_CACHE_TTL` | `300` | Default cache TTL (seconds) |

## Development

```bash
composer test      # Pest
composer analyse   # PHPStan, level 8
composer lint      # Pint (check only)
composer ci        # all of the above
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Credits

- [Selim Görmüş](https://github.com/siberfx)
- Built on the official [ploi/ploi-php-sdk](https://github.com/ploi/ploi-php-sdk) by the Ploi team.

## License

MIT. See [LICENSE](LICENSE).
