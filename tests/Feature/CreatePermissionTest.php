<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CreatePermissionTest extends TestCase
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

    public function test_user_without_create_permissions_cannot_create_resources(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, [
            'all_expenses',
            'all_params',
            'all_users',
            'daily',
            'devices',
            'deviceTimes',
            'deviceTypes',
            'maintenaces',
            'medias',
            'orders',
            'products',
        ]);

        $this->actingAsUser($user);

        foreach ($this->createEndpoints() as $uri => $payload) {
            $response = $this->postJson($uri, $payload);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Expected POST {$uri} to be forbidden without its create permission."
            );
        }
    }

    private function createEndpoints(): array
    {
        return [
            '/api/v1/admin/users' => [],
            '/api/v1/admin/media' => [],
            '/api/v1/admin/expenses' => [],
            '/api/v1/admin/parameter/params' => [],
            '/api/v1/admin/dailies' => [],
            '/api/v1/admin/orders' => [],
            '/api/v1/admin/products' => [],
            '/api/v1/admin/maintenances' => [],
            '/api/v1/admin/timers' => [],
            '/api/v1/admin/devices' => [],
            '/api/v1/admin/devices/create-order-device' => [],
            '/api/v1/admin/device-types' => [],
            '/api/v1/admin/device-times' => [],
            '/api/v1/admin/device-timer/individual-time' => [],
            '/api/v1/admin/device-timer/group-time' => [],
        ];
    }
}
