<?php

namespace Tests\Feature;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\Device\DeviceStatusEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Daily\Daily;
use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeviceTimerCalculationTest extends TestCase
{
    private array $tenantConnections = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMainDatabase();
        Config::set('activitylog.enabled', false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::setDefaultConnection('mysql');
        DB::purge('tenant');
        $this->tenantConnections = [];

        parent::tearDown();
    }

    public function test_session_cost_is_calculated_and_device_becomes_available_when_timer_finishes(): void
    {
        $tenantDatabase = $this->createTenantDatabase();
        $user = $this->createUser($tenantDatabase, ['update_device']);
        $now = Carbon::parse('2026-06-15 12:00:00');

        $this->useTenantDatabase($tenantDatabase);

        $deviceType = DeviceType::create(['name' => 'PlayStation']);
        $device = Device::create([
            'name' => 'PS5-01',
            'device_type_id' => $deviceType->id,
            'status' => DeviceStatusEnum::UNAVAILABLE->value,
        ]);
        $deviceTime = DeviceTime::create([
            'name' => 'Hourly',
            'rate' => 50,
            'device_type_id' => $deviceType->id,
        ]);
        $daily = Daily::create(['start_date_time' => $now->copy()->startOfDay()]);
        $session = SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id,
        ]);
        $bookedDevice = BookedDevice::create([
            'session_device_id' => $session->id,
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'device_time_id' => $deviceTime->id,
            'start_date_time' => $now->copy()->subHours(2),
            'status' => BookedDeviceEnum::ACTIVE->value,
        ]);

        DB::setDefaultConnection('mysql');
        Carbon::setTestNow($now);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/admin/device-timer/{$bookedDevice->id}/finish")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price', 100);

        $finished = BookedDevice::on('tenant')->findOrFail($bookedDevice->id);
        $device->refresh();

        $this->assertSame(BookedDeviceEnum::FINISHED->value, $finished->status);
        $this->assertSame(7200, (int) $finished->total_used_seconds);
        $this->assertEquals(100.00, (float) $finished->period_cost);
        $this->assertSame(DeviceStatusEnum::AVAILABLE->value, $device->status);
    }

    private function prepareMainDatabase(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('mysql');
        DB::setDefaultConnection('mysql');

        Artisan::call('migrate:fresh', [
            '--database' => 'mysql',
            '--path' => 'database/migrations/Main',
            '--force' => true,
        ]);
    }

    private function createTenantDatabase(): string
    {
        $database = 'file:tenant_'.Str::uuid().'?mode=memory&cache=shared';
        $this->tenantConnections[] = new \PDO('sqlite:'.$database);

        $this->useTenantDatabase($database);

        collect(File::files(database_path('migrations/Tenant')))
            ->reject(fn ($migration) => str_contains($migration->getFilename(), 'optimize_activity_log_for_batch'))
            ->each(function ($migration): void {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/Tenant/'.$migration->getFilename(),
                    '--force' => true,
                ]);
            });

        return $database;
    }

    private function useTenantDatabase(string $database): void
    {
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
    }

    private function createUser(string $tenantDatabase, array $permissions): User
    {
        DB::setDefaultConnection('mysql');

        $permissionModels = [];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
            $permissionModels[] = Permission::findOrCreate($permission, 'web');
        }

        $user = User::create([
            'name' => 'Timer Tester',
            'email' => 'timer-tester@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
            'database_name' => $tenantDatabase,
        ]);

        $user->givePermissionTo($permissionModels);

        return $user;
    }
}
