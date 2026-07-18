<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error(__('auth.not_authenticated'), [], HttpStatusCode::UNAUTHORIZED);
        }

        if (! $user->is_active) {
            return ApiResponse::error(__('auth.inactive_account'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        }

        if (! $user->database_name) {
            return ApiResponse::error('No tenant database assigned', [], HttpStatusCode::FORBIDDEN);
        }

        try {
            if (app()->environment('testing') && config('database.connections.tenant.driver') === 'sqlite') {
                Config::set('database.connections.tenant.database', $user->database_name);
                DB::purge('tenant');
                DB::reconnect('tenant');
                DB::setDefaultConnection('tenant');

                return $next($request);
            }

            // إعداد اتصال tenant
            $tenantConnection = config('database.connections.tenant');

            Config::set('database.connections.tenant', [
                'driver' => 'mysql',
                'host' => $user->database_host ?? $tenantConnection['host'],
                'port' => $tenantConnection['port'],
                'database' => $user->database_name ?? $tenantConnection['database'],
                'username' => $user->database_username ?? $tenantConnection['username'],
                'password' => $user->database_password ?? $tenantConnection['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ]);

            // تنظيف وإعادة الاتصال
            DB::purge('tenant');
            DB::reconnect('tenant');
            DB::setDefaultConnection('tenant');

            logger()->info('Tenant connected successfully.', [
                'user_id' => $user->id,
                'database' => $user->database_name,
            ]);
        } catch (\Throwable $e) {
            logger()->error('Tenant database connection failed.', [
                'user_id' => $user->id,
                'database' => $user->database_name,
                'exception' => $e,
            ]);

            return ApiResponse::error(
                'حدثت مشكلة في الاتصال بقاعدة البيانات. يرجى مراجعة الدعم الفني.',
                [],
                HttpStatusCode::UNPROCESSABLE_ENTITY
            );
        }

        return $next($request);
    }
}
