<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Ploi\Ploi as SdkPloi;
use Siberfx\PloiLaravel\Exceptions\ConnectionFailedException;
use Siberfx\PloiLaravel\Exceptions\InvalidConfigurationException;
use Siberfx\PloiLaravel\Exceptions\ValidationException;
use Siberfx\PloiLaravel\Facades\Ploi;
use Siberfx\PloiLaravel\Http\PloiResponse;
use Siberfx\PloiLaravel\PloiClient;

it('sends SDK resource calls through Laravel\'s HTTP client', function () {
    Ploi::fake(['servers/1' => ['data' => ['id' => 1, 'name' => 'web-1']]]);

    $response = Ploi::server(1)->get();

    expect($response)->toBeInstanceOf(PloiResponse::class)
        ->and($response->data('name'))->toBe('web-1')
        ->and($response->status())->toBe(200);

    Ploi::assertSent(fn (Request $request) => $request->url() === 'https://ploi.io/api/servers/1'
        && $request->method() === 'GET'
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('Accept', 'application/json'));
});

it('keeps the plain SDK response API working', function () {
    Ploi::fake(['servers/1' => ['data' => ['id' => 1, 'name' => 'web-1']]]);

    $response = Ploi::server(1)->get();

    expect($response->getData()->name)->toBe('web-1')
        ->and($response->getJson()->data->id)->toBe(1);
});

it('forwards request bodies the SDK builds', function () {
    Ploi::fake();

    Ploi::server(1)->sites(2)->deployment()->updateDeployScript('php artisan migrate --force');

    Ploi::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === 'https://ploi.io/api/servers/1/sites/2/deploy/script'
        && $request->data() === ['deploy_script' => 'php artisan migrate --force']);
});

it('collects list responses', function () {
    Ploi::fake(['servers*' => ['data' => [['id' => 1], ['id' => 2]], 'meta' => ['current_page' => 1, 'last_page' => 1]]]);

    $response = Ploi::server()->get();

    expect($response->collect()->pluck('id')->all())->toBe([1, 2])
        ->and($response->currentPage())->toBe(1)
        ->and($response->hasMorePages())->toBeFalse();
});

it('pages through every result lazily', function () {
    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $page = (int) $query['page'];

        return Http::response([
            'data' => $page === 1 ? [['id' => 1], ['id' => 2]] : [['id' => 3]],
            'meta' => ['current_page' => $page, 'last_page' => 2],
        ]);
    });

    $ids = Ploi::lazy(fn (int $page) => Ploi::server()->page($page, 2))->pluck('id')->all();

    expect($ids)->toBe([1, 2, 3]);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'page=2&per_page=2'));
});

it('retries rate limits and server errors', function () {
    Ploi::fake(['servers*' => Http::sequence()
        ->push(['message' => 'Too many'], 429)
        ->push(['message' => 'Oops'], 502)
        ->push(['data' => []], 200)]);

    expect(Ploi::server()->get()->status())->toBe(200);
    Http::assertSentCount(3);
});

it('does not retry client errors', function () {
    Ploi::fake(['servers*' => Ploi::response(['message' => 'Invalid'], 422)]);

    expect(fn () => Ploi::server()->get())->toThrow(ValidationException::class);
    Http::assertSentCount(1);
});

it('turns connection errors into a package exception after retrying', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('timed out');
    });

    expect(fn () => Ploi::server()->get())->toThrow(ConnectionFailedException::class, 'timed out')
        ->and($attempts)->toBe(3); // 1 try + 2 retries (config default)
});

it('uses the token of the requested connection', function () {
    Ploi::fake();

    Ploi::connection('agency')->server()->get();

    Ploi::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer agency-token'));
});

it('builds clients for tokens outside the config', function () {
    Ploi::fake();

    $client = Ploi::withToken('tenant-token');
    $client->server()->get();

    expect($client->name())->toStartWith('token-')->not->toContain('tenant-token');
    Ploi::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer tenant-token'));
});

it('lets one connection override HTTP options', function () {
    config()->set('ploi.connections.eu', ['token' => 'eu-token', 'base_url' => 'https://eu.example.test/api/']);
    Http::fake(['eu.example.test/*' => Http::response(['data' => []])]);

    Ploi::connection('eu')->server()->get();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://eu.example.test/api/servers'));
});

it('rejects unknown connections and missing tokens', function () {
    expect(fn () => Ploi::connection('nope'))->toThrow(InvalidConfigurationException::class, '[nope] is not defined');

    config()->set('ploi.connections.empty', ['token' => null]);

    expect(fn () => Ploi::connection('empty'))->toThrow(InvalidConfigurationException::class, 'has no API token');
});

it('caches responses as plain arrays', function () {
    Ploi::fake(['servers*' => ['data' => [['id' => 1]]]]);

    $first = Ploi::remember('servers', fn (PloiClient $ploi) => $ploi->server()->get());
    $second = Ploi::remember('servers', fn (PloiClient $ploi) => $ploi->server()->get());

    expect($first)->toBe(['data' => [['id' => 1]]])
        ->and($second)->toBe($first)
        ->and(Cache::get('ploi:main:servers'))->toBe($first);
    Http::assertSentCount(1);

    expect(Ploi::forget('servers'))->toBeTrue();
});

it('triggers deployments with the shortcut', function () {
    Ploi::fake();

    Ploi::deploy(1, 2);

    Ploi::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://ploi.io/api/servers/1/sites/2/deploy');
});

it('offers a configured request for endpoints the SDK lacks', function () {
    Ploi::fake(['user' => ['data' => ['name' => 'Selim']]]);

    expect(Ploi::request()->get('user')->json('data.name'))->toBe('Selim');
});

it('injects the default client for the SDK and package classes', function () {
    expect(app(SdkPloi::class))->toBeInstanceOf(PloiClient::class)
        ->and(app(PloiClient::class)->name())->toBe('main')
        ->and(app(PloiClient::class)->getApiToken())->toBe('test-token');
});
