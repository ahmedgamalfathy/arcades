<?php

namespace Tests\Feature;

use App\Enums\Device\DeviceStatusEnum;
use App\Models\Device\Device;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantAndPermissionSecurityTest extends TestCase
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
        DB::setDefaultConnection('mysql');
        DB::purge('tenant');
        $this->tenantConnections = [];

        parent::tearDown();
    }

    public function test_tenant_user_cannot_see_devices_from_another_tenant(): void
    {
        $tenantA = $this->createTenantDatabase();
        $tenantB = $this->createTenantDatabase();
        $userA = $this->createUser($tenantA, ['devices']);
        $this->createUser($tenantB, ['devices'], 'tenant-b-user@example.com');

        $this->useTenantDatabase($tenantB);
        $deviceType = DeviceType::create(['name' => 'Tenant B Type']);
        $tenantBDevice = Device::create([
            'name' => 'Tenant B Device',
            'device_type_id' => $deviceType->id,
            'status' => DeviceStatusEnum::AVAILABLE->value,
        ]);

        DB::setDefaultConnection('mysql');
        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/admin/devices');

        if ($response->getStatusCode() === 403) {
            $response->assertForbidden();

            return;
        }

        $response->assertOk();
        $response->assertJsonMissing(['deviceId' => $tenantBDevice->id]);
        $response->assertJsonMissing(['name' => 'Tenant B Device']);
    }

    public function test_cashier_without_view_reports_permission_cannot_access_reports(): void
    {
        $tenantDatabase = $this->createTenantDatabase();
        $cashier = $this->createCashierUser($tenantDatabase);

        Sanctum::actingAs($cashier);

        $this->getJson('/api/v1/admin/reports/dailyStatusData')
            ->assertForbidden();
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

    private function createUser(string $tenantDatabase, array $permissions, string $email = 'tenant-a-user@example.com'): User
    {
        DB::setDefaultConnection('mysql');

        $permissionModels = [];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
            $permissionModels[] = Permission::findOrCreate($permission, 'web');
        }

        $user = User::create([
            'name' => 'Tenant User',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => 1,
            'database_name' => $tenantDatabase,
        ]);

        $user->givePermissionTo($permissionModels);

        return $user;
    }

    private function createCashierUser(string $tenantDatabase): User
    {
        DB::setDefaultConnection('mysql');

        Permission::findOrCreate('devices', 'api');
        Permission::findOrCreate('view_reports', 'api');
        $devices = Permission::findOrCreate('devices', 'web');
        Permission::findOrCreate('view_reports', 'web');

        $role = Role::findOrCreate('Cashier', 'web');
        $role->syncPermissions([$devices]);

        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
            'database_name' => $tenantDatabase,
        ]);

        $cashier->assignRole($role);

        return $cashier;
    }
}
