<?php

namespace Tests\Feature;

use App\Enums\Device\DeviceStatusEnum;
use App\Models\Device\Device;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantAndPermissionSecurityTest extends TestCase
{
    use InteractsWithTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantTesting();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantTesting();
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

        $this->actingAsUser($userA);

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

        $this->actingAsUser($cashier);

        $this->getJson('/api/v1/admin/reports/dailyStatusData')
            ->assertForbidden();
    }

    public function test_user_without_view_daily_activity_permission_cannot_access_daily_activity(): void
    {
        $tenantDatabase = $this->createTenantDatabase();
        $user = $this->createUser($tenantDatabase, ['daily']);

        $this->actingAsUser($user);

        $this->getJson('/api/v1/admin/dailyActivity?dailyId=1')
            ->assertForbidden();
    }

    public function test_user_management_cannot_access_users_from_another_account(): void
    {
        $tenantA = $this->createTenantDatabase();
        $tenantB = $this->createTenantDatabase();
        $actor = $this->createUser($tenantA, [
            'all_users',
            'edit_user',
            'update_user',
            'destroy_user',
            'restore_user',
            'force_delete_user',
        ]);
        $foreignOwner = $this->createUser($tenantB, [], 'tenant-b-owner@example.com');
        $foreignUser = $this->createManagedUser($foreignOwner, 'tenant-b-staff@example.com');
        $role = Role::findOrCreate('Operator', 'web');

        $this->actingAsUser($actor);

        $this->getJson("/api/v1/admin/users/{$foreignUser->id}")
            ->assertNotFound();

        $this->putJson("/api/v1/admin/users/{$foreignUser->id}", [
            'name' => 'Compromised Name',
            'email' => 'compromised@example.com',
            'isActive' => 1,
            'roleId' => $role->id,
        ])->assertNotFound();

        $this->deleteJson("/api/v1/admin/users/{$foreignUser->id}")
            ->assertNotFound();

        $foreignUser->delete();

        $this->postJson("/api/v1/admin/users/{$foreignUser->id}/restore")
            ->assertNotFound();
        $this->deleteJson("/api/v1/admin/users/{$foreignUser->id}/force")
            ->assertNotFound();
        $this->deleteJson("/api/v1/admin/users/{$foreignUser->id}/force-delete")
            ->assertNotFound();

        $foreignUser->refresh();
        $this->assertTrue($foreignUser->trashed());
        $this->assertSame('Tenant Staff', $foreignUser->name);
        $this->assertSame('tenant-b-staff@example.com', $foreignUser->email);
    }

    public function test_user_management_still_manages_users_in_the_same_account(): void
    {
        $tenant = $this->createTenantDatabase();
        $actor = $this->createUser($tenant, [
            'all_users',
            'edit_user',
            'update_user',
            'destroy_user',
            'restore_user',
            'force_delete_user',
        ]);
        $managedUser = $this->createManagedUser($actor, 'staff@example.com');
        $role = Role::findOrCreate('Operator', 'web');

        $this->actingAsUser($actor);

        $this->getJson("/api/v1/admin/users/{$managedUser->id}")
            ->assertOk();

        $this->putJson("/api/v1/admin/users/{$managedUser->id}", [
            'name' => 'Updated Staff',
            'email' => 'updated-staff@example.com',
            'isActive' => 1,
            'roleId' => $role->id,
        ])->assertOk();

        $this->deleteJson("/api/v1/admin/users/{$managedUser->id}")
            ->assertOk();
        $this->postJson("/api/v1/admin/users/{$managedUser->id}/restore")
            ->assertOk();
        $this->deleteJson("/api/v1/admin/users/{$managedUser->id}/force")
            ->assertOk();

        $this->assertNull(User::withTrashed()->find($managedUser->id));
    }

    private function createManagedUser(User $owner, string $email): User
    {
        DB::setDefaultConnection('mysql');

        return User::create([
            'name' => 'Tenant Staff',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => 1,
            'user_id' => $owner->id,
            'database_name' => $owner->database_name,
        ]);
    }
}
