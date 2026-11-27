<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Siberfx\PloiLaravel\Facades\Ploi;

it('checks the token', function () {
    Ploi::fake(['user' => ['data' => ['name' => 'Selim', 'email' => 'dev@example.test']]]);

    $this->artisan('ploi:check')
        ->expectsOutputToContain('Connected to Ploi on connection [main] as Selim · dev@example.test.')
        ->assertSuccessful();
});

it('reports a rejected token', function () {
    config()->set('ploi.retry.times', 0);
    Ploi::fake(['user' => Ploi::response(['message' => 'Unauthenticated.'], 401)]);

    $this->artisan('ploi:check')
        ->expectsOutputToContain('Ploi API request failed with status 401: Unauthenticated.')
        ->assertFailed();
});

it('reports a missing token', function () {
    config()->set('ploi.connections.main.token', null);

    $this->artisan('ploi:check')
        ->expectsOutputToContain('has no API token')
        ->assertExitCode(2);
});

it('lists servers', function () {
    Ploi::fake(['servers*' => ['data' => [['id' => 7, 'name' => 'web-1', 'ip_address' => '10.0.0.1', 'status' => 'active']]]]);

    $this->artisan('ploi:servers')
        ->expectsTable(['id', 'name', 'ip_address', 'status'], [['7', 'web-1', '10.0.0.1', 'active']])
        ->assertSuccessful();
});

it('lists servers as json on another connection', function () {
    Ploi::fake(['servers*' => ['data' => [['id' => 7, 'name' => 'web-1']]]]);

    $this->artisan('ploi:servers', ['--connection' => 'agency', '--json' => true])
        ->expectsOutputToContain('"name": "web-1"')
        ->assertSuccessful();

    Ploi::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer agency-token'));
});

it('lists the sites of a server', function () {
    Ploi::fake(['servers/7/sites*' => ['data' => [['id' => 3, 'domain' => 'example.test', 'status' => 'active', 'has_repository' => true]]]]);

    $this->artisan('ploi:sites', ['server' => 7])
        ->expectsTable(['id', 'domain', 'status', 'has_repository'], [['3', 'example.test', 'active', 'yes']])
        ->assertSuccessful();
});

it('rejects a non-numeric server id', function () {
    Ploi::fake();

    $this->artisan('ploi:sites', ['server' => 'abc'])
        ->expectsOutputToContain('must be a positive Ploi ID')
        ->assertExitCode(2);

    Ploi::assertNothingSent();
});

it('deploys a site', function () {
    Ploi::fake();

    $this->artisan('ploi:deploy', ['server' => 7, 'site' => 3])
        ->expectsOutputToContain('Deployment of site 3 on server 7 started.')
        ->assertSuccessful();

    Ploi::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://ploi.io/api/servers/7/sites/3/deploy');
});

it('deploys a staging site to production', function () {
    Ploi::fake();

    $this->artisan('ploi:deploy', ['server' => 7, 'site' => 3, '--to-production' => true])->assertSuccessful();

    Ploi::assertSent(fn (Request $request) => $request->url() === 'https://ploi.io/api/servers/7/sites/3/deploy-to-production');
});

it('refuses to deploy from a production app without --force when it cannot ask', function () {
    $this->app['env'] = 'production';
    Ploi::fake();

    $this->artisan('ploi:deploy', ['server' => 7, 'site' => 3, '--no-interaction' => true])
        ->expectsOutputToContain('Pass --force to deploy without confirmation.')
        ->assertFailed();

    Ploi::assertNothingSent();
});

it('deploys from a production app with --force', function () {
    $this->app['env'] = 'production';
    Ploi::fake();

    $this->artisan('ploi:deploy', ['server' => 7, 'site' => 3, '--force' => true])
        ->assertSuccessful();

    Ploi::assertSent(fn (Request $request) => $request->url() === 'https://ploi.io/api/servers/7/sites/3/deploy');
});
