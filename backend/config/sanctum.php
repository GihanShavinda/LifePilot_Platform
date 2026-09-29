<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these domains are treated as first-party SPA requests
    | and may authenticate using Laravel's session cookies.
    |
    */

    'stateful' => explode(
        ',',
        env(
            'SANCTUM_STATEFUL_DOMAINS',
            sprintf(
                '%s%s',
                implode(',', [
                    'localhost',
                    'localhost:5174',
                    'localhost:8000',
                    '127.0.0.1',
                    '127.0.0.1:5174',
                    '127.0.0.1:8000',
                    '::1',
                ]),
                Sanctum::currentApplicationUrlWithPort()
            )
        )
    ),

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guard' => [
        'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Expiration
    |--------------------------------------------------------------------------
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */

    'token_prefix' => env(
        'SANCTUM_TOKEN_PREFIX',
        ''
    ),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [

        'authenticate_session' =>
            AuthenticateSession::class,

        'encrypt_cookies' =>
            EncryptCookies::class,

        'validate_csrf_token' =>
            ValidateCsrfToken::class,
    ],

];