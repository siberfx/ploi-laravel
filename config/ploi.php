<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The Ploi account used when you call the facade without naming one,
    | e.g. Ploi::server()->get(). Switch with Ploi::connection('name').
    |
    */

    'default' => env('PLOI_CONNECTION', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | One entry per Ploi account (API token). Any HTTP option below can be
    | overridden per connection, e.g. a longer timeout for one account.
    | Create tokens at https://ploi.io/profile/api-keys.
    |
    */

    'connections' => [

        'main' => [
            'token' => env('PLOI_API_TOKEN'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Options
    |--------------------------------------------------------------------------
    |
    | Requests go through Laravel's HTTP client, so Http::fake(), Laravel's
    | HTTP events and these options all apply. Retries only happen on
    | connection errors, 429 (rate limit) and 5xx responses.
    |
    */

    'base_url' => env('PLOI_BASE_URL', 'https://ploi.io/api'),

    'timeout' => (int) env('PLOI_TIMEOUT', 30),

    'connect_timeout' => (int) env('PLOI_CONNECT_TIMEOUT', 10),

    'retry' => [
        'times' => (int) env('PLOI_RETRY_TIMES', 2),
        'sleep' => (int) env('PLOI_RETRY_SLEEP', 500), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Used by Ploi::remember(). Null store means your default cache store.
    | Keys are prefixed with the prefix and the connection name.
    |
    */

    'cache' => [
        'store' => env('PLOI_CACHE_STORE'),
        'prefix' => 'ploi',
        'ttl' => (int) env('PLOI_CACHE_TTL', 300), // seconds
    ],

];
