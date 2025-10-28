<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Setting\Param\Param;
use App\Notifications\BookedDeviceStatusNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Events\BookedDeviceExpireTime;

class CheckBookedDevicesNotifications extends Command
{
    protected $signature = 'notifications:check-booked-devices';
    protected $description = 'Check booked devices and send notifications for all tenants';

    public function handle(): int
    {
        // الرجوع للاتصال الرئيسي للحصول على المستخدمين
        DB::setDefaultConnection('mysql');
        
        // جلب كل المستخدمين النشطين اللي عندهم database
        $MainUsers = User::where('is_active', 1)
            ->whereNotNull('database_name')
            ->distinct('database_name')
            ->get(['id', 'database_name', 'database_username', 'database_password']);
        
        if ($MainUsers->isEmpty()) {
            $this->info('No active Users found');
            return self::SUCCESS;
        }

        $this->info("Found {$MainUsers->count()} Main Users");

        foreach ($MainUsers as $MainUser) {
            $this->info("Processing tenant database: {$MainUser->database_name}");
            
            try {
                $this->connectToTenant($MainUser);
                $this->processNotifications($MainUser->database_name);
            } catch (\Exception $e) {
                $this->error("Error for {$MainUser->database_name}: {$e->getMessage()}");
                Log::error('Command notification error', [
                    'database' => $MainUser->database_name,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        // العودة للاتصال الرئيسي
        DB::setDefaultConnection('mysql');
        
        return self::SUCCESS;
    }

    private function connectToTenant($user): void
    {
        // نفس منطق الـ TenantMiddleware
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => env('TENANT_DB_HOST', '127.0.0.1'),
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
        
        // اختبار الاتصال
        DB::connection('tenant')->getPdo();
        
        $this->info("✅ Connected to tenant database: {$user->database_name}");
    }

    private function processNotifications($databaseName): void
    {
        $now = Carbon::now();
        $users = User::on('mysql')->where('is_active', 1)->get();
        
        if ($users->isEmpty()) {
            $this->info("No active users in {$databaseName}");
            return;
        }
    $param = Param::on('tenant')
    ->where('parameter_order', 1)
    ->first();

        if (!$param || !is_numeric($param->type)) {
            $this->warn("Invalid default time notification in {$databaseName}");
            return;
        }

        $defaultTimeNotification = (int) $param->type;
        
        if (!$defaultTimeNotification) {
            $this->warn("No default time notification found in {$databaseName}");
            return;
        }

        // الأجهزة اللي على وشك الانتهاء
        $bookedDevices = BookedDevice::on('tenant')
            ->with(['sessionDevice', 'deviceType', 'deviceTime', 'device'])
            ->where('is_notification_sent',false)
            ->where('status', '!=', 'finished')
            ->whereBetween('end_date_time', [
                $now->copy()->addMinutes($defaultTimeNotification),
                $now->copy()->addMinutes($defaultTimeNotification + 1)
            ])->get();

        // الأجهزة اللي انتهى وقتها
        $expiredBookedDevices = BookedDevice::on('tenant')
            ->with(['sessionDevice', 'deviceType', 'deviceTime', 'device'])
            ->where('is_notification_sent',false)
            ->where('status', '!=', 'finished')
            ->where('end_date_time', '<=', $now)
            ->get();

        $notificationCount = 0;

        // إرسال إشعارات الأجهزة اللي على وشك الانتهاء
        foreach ($bookedDevices as $booked) {
            $booked->update(['is_notification_sent' => true]);
            $notificationData = [
                "sessionDevice" => $booked->sessionDevice->id,
                "deviceTypeName" => $booked->deviceType->name,
                "deviceTimeName" => $booked->deviceTime->name,
                "deviceName" => $booked->device->name,
                "bookedDeviceId" => $booked->id,
                "message" => "{$defaultTimeNotification} دقيقة متبقية على الجهاز",
            ];

        try {
            broadcast(new BookedDeviceExpireTime($notificationData));
            $this->info("✅ Broadcast sent for device: {$booked->device->name}");
        } catch (\Exception $e) {
            $this->error("Failed to broadcast: {$e->getMessage()}");
        }
            foreach ($users as $user) {
                try {
                    $user->notify(new BookedDeviceStatusNotification([
                        "sessionDevice" => $booked->sessionDevice->id,
                        "deviceTypeName" => $booked->deviceType->name,
                        "deviceTimeName" => $booked->deviceTime->name,
                        "deviceName" => $booked->device->name,
                        "bookedDeviceId" => $booked->id,
                        "message" => "متبقى على الجهاز {$defaultTimeNotification} دقائق",
                        "userId" => $user->id,
                        "created_at" => now(),
                        "updated_at" => now(),
                    ]));
                    $notificationCount++;
                } catch (\Exception $e) {
                    $this->error("Failed to send notification: {$e->getMessage()}");
                }
            }
        }

        // إرسال إشعارات الأجهزة المنتهية
        foreach ($expiredBookedDevices as $booked) {
            $booked->update(['is_notification_sent' => true]);
            $notificationData = [
                "sessionDevice" => $booked->sessionDevice->id,
                "deviceTypeName" => $booked->deviceType->name,
                "deviceTimeName" => $booked->deviceTime->name,
                "deviceName" => $booked->device->name,
                "bookedDeviceId" => $booked->id,
                "message" => "انتهى الوقت المتبقى على الجهاز",
            ];

        try {
            broadcast(new BookedDeviceExpireTime($notificationData));
            $this->info("✅ Broadcast sent for device: {$booked->device->name}");
        } catch (\Exception $e) {
            $this->error("Failed to broadcast: {$e->getMessage()}");
        }
            foreach ($users as $user) {
                try {
                    $user->notify(new BookedDeviceStatusNotification([
                        "sessionDevice" => $booked->sessionDevice->id,
                        "deviceTypeName" => $booked->deviceType->name,
                        "deviceTimeName" => $booked->deviceTime->name,
                        "deviceName" => $booked->device->name,
                        "bookedDeviceId" => $booked->id,
                        "message" => "انتهى الوقت المتبقى على الجهاز",
                        "userId" => $user->id,
                        "created_at" => now(),
                        "updated_at" => now(),
                    ]));
                    $notificationCount++;
                } catch (\Exception $e) {
                    $this->error("Failed to send notification: {$e->getMessage()}");
                }
            }
        }

        $this->info("✅ {$databaseName}: Sent {$notificationCount} notifications");
    }
}