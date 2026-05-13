<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Replace the default Authenticate middleware with our API-safe version.
        // This ensures that unauthenticated requests always throw an
        // AuthenticationException (→ 401 JSON) instead of redirecting to
        // a non-existent "login" named route.
        $middleware->alias([
            'auth'               => \App\Http\Middleware\Authenticate::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unauthenticated → 401
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            return response()->json(
                ['message' => 'No autenticado.'],
                Response::HTTP_UNAUTHORIZED
            );
        });

        // Unauthorized (missing Spatie role/permission) → 403
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, Request $request) {
            return response()->json(
                ['message' => 'No tienes permiso para realizar esta acción.'],
                Response::HTTP_FORBIDDEN
            );
        });
    })->create();
