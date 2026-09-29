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

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['household.role' => RequireHouseholdRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e): bool => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!$request->is('api/*')) return null;
            return response()->json(['success'=>false,'error'=>['code'=>'VALIDATION_ERROR','message'=>'The given data was invalid.','details'=>$e->errors()]], 422);
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (!$request->is('api/*')) return null;
            return response()->json(['success'=>false,'error'=>['code'=>'UNAUTHENTICATED','message'=>'Authentication is required.','details'=>(object)[]]], 401);
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (!$request->is('api/*')) return null;
            return response()->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>$e->getMessage() ?: 'You are not allowed to perform this action.','details'=>(object)[]]], 403);
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (!$request->is('api/*')) return null;
            $status=$e->getStatusCode();
            return response()->json(['success'=>false,'error'=>['code'=>'HTTP_'.$status,'message'=>$e->getMessage() ?: 'Request failed.','details'=>(object)[]]], $status);
        });
    })->create();
