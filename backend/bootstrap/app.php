<?php

use App\Domain\Auth\Middleware\RequireHouseholdRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;


return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Stateful API / Sanctum
        |--------------------------------------------------------------------------
        |
        | Required for React SPA cookie/session authentication.
        |
        */

        $middleware->statefulApi();

        /*
        |--------------------------------------------------------------------------
        | Middleware Aliases
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            'household.role' => RequireHouseholdRole::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | Force API Errors To JSON
        |--------------------------------------------------------------------------
        */

        $exceptions->shouldRenderJsonWhen(
            function (
                Request $request,
                \Throwable $e
            ): bool {
                return $request->is('api/*')
                    || $request->expectsJson();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Validation Error
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                ValidationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'details' => $exception->errors(),
                    ],
                ], 422);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Authentication Error
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                AuthenticationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Authentication is required.',
                        'details' => (object) [],
                    ],
                ], 401);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Authorization Error
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                AuthorizationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' =>
                            $exception->getMessage()
                            ?: 'You are not allowed to perform this action.',
                        'details' => (object) [],
                    ],
                ], 403);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | HTTP Errors
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                HttpExceptionInterface $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                $status = $exception->getStatusCode();

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'HTTP_'.$status,
                        'message' =>
                            $exception->getMessage()
                            ?: 'Request failed.',
                        'details' => (object) [],
                    ],
                ], $status);
            }
        );
    })

    ->create();