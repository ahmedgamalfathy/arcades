<?php

namespace Tests\Feature;

use App\Enums\Device\DeviceStatusEnum;
use App\Models\Device\Device;
use App\Models\Device\DeviceType\DeviceType;
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
}
