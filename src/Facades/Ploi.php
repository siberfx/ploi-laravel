<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use Siberfx\PloiLaravel\PloiClient;
use Siberfx\PloiLaravel\PloiManager;

/**
 * @method static \Siberfx\PloiLaravel\PloiClient connection(string|null $name = null)
 * @method static \Siberfx\PloiLaravel\PloiClient withToken(string $token, array<string, mixed> $options = [])
 * @method static string getDefaultConnection()
 * @method static void setDefaultConnection(string $name)
 * @method static void purge(string|null $name = null)
 * @method static \Illuminate\Http\Client\Factory fake(array<string, mixed> $responses = [])
 * @method static \GuzzleHttp\Promise\PromiseInterface response(array<array-key, mixed> $body = [], int $status = 200, array<string, string> $headers = [])
 * @method static void assertSent(callable $callback)
 * @method static void assertNothingSent()
 * @method static \Ploi\Resources\Server server(int|null $id = null)
 * @method static \Ploi\Resources\Server servers(int|null $id = null)
 * @method static \Ploi\Resources\Project project(int|null $id = null)
 * @method static \Ploi\Resources\Project projects(int|null $id = null)
 * @method static \Ploi\Resources\Script scripts(int|null $id = null)
 * @method static \Ploi\Resources\StatusPage statusPage(int|null $id = null)
 * @method static \Ploi\Resources\User user()
 * @method static \Ploi\Resources\WebserverTemplate webserverTemplates(int|null $id = null)
 * @method static \Ploi\Resources\FileBackup fileBackup(int|null $id = null)
 * @method static \Ploi\Resources\FileBackup fileBackups(int|null $id = null)
 * @method static \Ploi\Http\Response makeAPICall(string $url, string $method = 'get', array<string, mixed> $options = [])
 * @method static \Illuminate\Http\Client\PendingRequest request()
 * @method static \Illuminate\Support\LazyCollection<int, mixed> lazy(\Closure $page)
 * @method static mixed remember(string $key, \Closure $callback, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool forget(string $key)
 * @method static \Ploi\Http\Response deploy(int $server, int $site)
 *
 * @see PloiManager
 * @see PloiClient
 */
class Ploi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PloiManager::class;
    }
}
