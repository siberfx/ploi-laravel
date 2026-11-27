# Changelog

All notable changes to `siberfx/ploi-laravel` are documented here.
This project follows [Semantic Versioning](https://semver.org).

## 1.0.1 - 2026-11-28

- `ploi:deploy` refuses to deploy to production without a terminal and asks for `--force`, instead of waiting on a confirmation prompt that never arrives.
- README badges and links use absolute URLs, so they work on Packagist too. The PHPStan badge links to the code-quality workflow, because `phpstan.neon.dist` is not in the dist archive.

## 1.0.0 - 2026-11-27

First release.

- `PloiClient` extends the official `ploi/ploi-php-sdk` client and sends every SDK call through Laravel's HTTP client: `Http::fake()`, Laravel's HTTP events, timeouts and retries (connection errors, 429 and 5xx) all apply.
- `Ploi` facade and `PloiManager` with several accounts in `config/ploi.php`, per-connection HTTP options, and `Ploi::withToken()` for tokens stored per tenant.
- `PloiResponse` adds `data()`, `json()`, `collect()`, `meta()`, `currentPage()`, `lastPage()` and `hasMorePages()`, and keeps the SDK's `getData()` / `getJson()`.
- `Ploi::lazy()` walks every page of a list as a `LazyCollection`.
- `Ploi::remember()` / `Ploi::forget()` cache responses as plain arrays (safe with Laravel 13's `serializable_classes => false`).
- Typed exceptions for 401, 404, 405, 422 (with `errors()`), 429 (with `retryAfter()`), 5xx and 503, each extending the matching SDK exception; other error statuses (such as 403) throw `RequestFailedException` instead of passing silently.
- `Ploi::fake()`, `Ploi::response()`, `Ploi::assertSent()` and `Ploi::assertNothingSent()` for tests.
- Artisan commands: `ploi:check`, `ploi:servers`, `ploi:sites` and `ploi:deploy`.
- Supports PHP 8.3, 8.4 and 8.5 with Laravel 12 and 13.
