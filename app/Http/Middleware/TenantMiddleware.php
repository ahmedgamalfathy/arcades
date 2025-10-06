<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Enums\ResponseCode\HttpStatusCode;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error(__('auth.not_authenticated'), [], HttpStatusCode::UNAUTHORIZED);
        }

        if (!$user->is_active) {
            return ApiResponse::error(__('auth.inactive_account'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        }

        if (!$user->database_name) {
            return ApiResponse::error("No tenant database assigned", [], HttpStatusCode::FORBIDDEN);
        }

        try {
            // إعداد اتصال الـ tenant
            Config::set('database.connections.tenant', [
                'driver' => 'mysql',
                'host' => $user->database_host ?? env('TENANT_DB_HOST', '127.0.0.1'),
                'port' => env('TENANT_DB_PORT', '3306'),
                'database' => $user->database_name ?? env('TENANT_DB_DATABASE'),
                'username' => $user->database_username ?? env('TENANT_DB_USERNAME', 'root'),
                'password' => $user->database_password ?? env('TENANT_DB_PASSWORD', ''),
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
            // DB::connection('tenant')->getPdo();
            Log::info('✅ Tenant connected successfully', [
                'user_id' => $user->id,
                'database' => $user->database_name,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Tenant connection failed', [
                'user_id' => $user->id,
                'database' => $user->database_name,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to connect to tenant database',
                $e->getMessage(),
                HttpStatusCode::UNPROCESSABLE_ENTITY
            );
        }

        return $next($request);
    }

}
