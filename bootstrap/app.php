<?php

use App\Helpers\ApiResponse;
use Illuminate\Foundation\Application;
use App\Http\Middleware\CheckPermission;
use App\Enums\ResponseCode\HttpStatusCode;
use Spatie\Permission\Middleware\RoleMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            // 'check.permission' => CheckPermission::class,
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
        return ApiResponse::error("You do not have permission.", [], HttpStatusCode::FORBIDDEN);
    });

    // unauthenticated (401)
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        return ApiResponse::error("Unauthenticated.", [], HttpStatusCode::UNAUTHORIZED);
    });
    })->create();
