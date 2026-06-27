<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithTenants
{
    private array $tenantConnections = [];

    protected function setUpTenantTesting(): void
    {
        $this->prepareMainDatabase();
        Config::set('activitylog.enabled', false);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDownTenantTesting(): void
    {
        DB::setDefaultConnection('mysql');
        DB::purge('tenant');
        $this->tenantConnections = [];
    }

    protected function prepareMainDatabase(): void
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

    protected function createTenantDatabase(): string
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

    protected function useTenantDatabase(string $database): void
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

    protected function createUser(string $tenantDatabase, array $permissions, string $email = 'user@example.com'): User
    {
        DB::setDefaultConnection('mysql');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => 1,
            'database_name' => $tenantDatabase,
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findByName($permission, 'api'));
            $user->givePermissionTo(Permission::findByName($permission, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function createCashierUser(string $tenantDatabase): User
    {
        DB::setDefaultConnection('mysql');

        Permission::findOrCreate('devices', 'api');
        Permission::findOrCreate('devices', 'web');
        Permission::findOrCreate('view_reports', 'api');
        Permission::findOrCreate('view_reports', 'web');

        $devicesApi = Permission::findByName('devices', 'api');
        $devicesWeb = Permission::findByName('devices', 'web');

        $roleApi = Role::findOrCreate('Cashier', 'api');
        $roleApi->syncPermissions([$devicesApi]);
        $roleWeb = Role::findOrCreate('Cashier', 'web');
        $roleWeb->syncPermissions([$devicesWeb]);

        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
            'database_name' => $tenantDatabase,
        ]);

        $cashier->assignRole($roleApi);
        $cashier->assignRole($roleWeb);

        return $cashier;
    }

    protected function actingAsUser(User $user): void
    {
        DB::setDefaultConnection('mysql');
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withToken($token);
    }
}
