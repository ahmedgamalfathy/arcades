<?php

namespace Tests\Feature;

use App\Enums\Device\DeviceStatusEnum;
use App\Models\Device\Device;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
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

    public function test_user_management_rejects_roles_above_the_current_users_authority(): void
    {
        $tenant = $this->createTenantDatabase();
        $actor = $this->createUser($tenant, ['update_user', 'devices']);
        $managedUser = $this->createManagedUser($actor, 'role-target@example.com');

        $allowedRole = Role::findOrCreate('Operator', 'web');
        $allowedRole->syncPermissions([
            Permission::findByName('devices', 'web'),
        ]);

        $crossGuardRole = Role::findOrCreate('API Operator', 'api');
        $crossGuardRole->syncPermissions([
            Permission::findByName('devices', 'api'),
        ]);
        $actor->revokePermissionTo(Permission::findByName('devices', 'api'));

        $elevatedPermission = Permission::findOrCreate('manage_everything', 'web');
        $elevatedRole = Role::findOrCreate('Account Administrator', 'web');
        $elevatedRole->syncPermissions([$elevatedPermission]);

        $protectedRole = Role::findOrCreate('super admin', 'web');
        $legacyProtectedRole = Role::findOrCreate('مدير عام', 'web');

        $this->actingAsUser($actor);

        foreach ([$elevatedRole->id, $protectedRole->id, $legacyProtectedRole->id, $crossGuardRole->id, 999999] as $forbiddenRoleId) {
            $this->putJson("/api/v1/admin/users/{$managedUser->id}", [
                'name' => 'Compromised Name',
                'email' => 'role-target@example.com',
                'isActive' => 1,
                'roleId' => $forbiddenRoleId,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('roleId');

            $managedUser->refresh();
            $this->assertSame('Tenant Staff', $managedUser->name);
            $this->assertFalse($managedUser->roles->contains('id', $forbiddenRoleId));
        }

        $this->putJson("/api/v1/admin/users/{$managedUser->id}", [
            'name' => 'Authorized Update',
            'email' => 'role-target@example.com',
            'isActive' => 1,
            'roleId' => $allowedRole->id,
        ])->assertOk();

        $managedUser->refresh();
        $this->assertSame('Authorized Update', $managedUser->name);
        $this->assertTrue($managedUser->roles->contains('id', $allowedRole->id));
    }

    public function test_user_service_rechecks_role_authority_when_request_validation_is_bypassed(): void
    {
        $tenant = $this->createTenantDatabase();
        $actor = $this->createUser($tenant, ['create_user']);
        $elevatedPermission = Permission::findOrCreate('manage_everything', 'web');
        $elevatedRole = Role::findOrCreate('Account Administrator', 'web');
        $elevatedRole->syncPermissions([$elevatedPermission]);

        $this->actingAs($actor, 'api');

        try {
            app(UserService::class)->createUser([
                'roleId' => $elevatedRole->id,
            ]);
            $this->fail('The service accepted a role above the current user authority.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roleId', $exception->errors());
        }

        $this->assertSame(1, User::count());

        $allowedRole = Role::findOrCreate('Operator', 'web');
        $createdUser = app(UserService::class)->createUser([
            'name' => 'Authorized Staff',
            'email' => 'authorized-staff@example.com',
            'password' => 'password123',
            'isActive' => 1,
            'roleId' => $allowedRole->id,
        ]);

        $this->assertSame($actor->id, $createdUser->user_id);
        $this->assertTrue($createdUser->roles->contains('id', $allowedRole->id));
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
