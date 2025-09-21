<?php

use App\Helpers\ApiResponse;
use Illuminate\Foundation\Application;
use App\Http\Middleware\CheckPermission;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Validation\UnauthorizedException;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
     $exceptions->render(function (UnauthorizedException $e, $request) {
       return ApiResponse::error("You do not have permission",[],HttpStatusCode::FORBIDDEN);
    });
    })->create();
