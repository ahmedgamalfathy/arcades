<?php

namespace Tests\Feature\Order;

use App\Enums\Order\OrderStatus;
use App\Models\Daily\Daily;
use App\Models\Order\Order;
use App\Models\Product\Product;
use Carbon\Carbon;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OrderTest extends TestCase
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

    public function test_user_can_create_order_with_correct_total_price(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_orders']);
        $this->useTenantDatabase($tenant);

        $daily = Daily::create(['start_date_time' => Carbon::now()]);
        $product = Product::create(['name' => 'Cola', 'price' => 15.00]);

        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/orders', [
            'name' => 'Order #1',
            'dailyId' => $daily->id,
            'isPaid' => true,
            'status' => OrderStatus::CONFIRMED->value,
            'orderItems' => [
                ['productId' => $product->id, 'qty' => 2],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $order = Order::on('tenant')->first();
        $this->assertEquals(30.00, (float) $order->price);
        $this->assertCount(1, $order->items);
    }

    public function test_create_order_fails_validation_without_items(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_orders']);
        $this->useTenantDatabase($tenant);

        $daily = Daily::create(['start_date_time' => Carbon::now()]);
        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/orders', [
            'name' => 'Empty Order',
            'dailyId' => $daily->id,
            'isPaid' => false,
            'status' => OrderStatus::PENDING->value,
            'orderItems' => [],
        ])->assertUnprocessable();
    }

    public function test_user_without_permission_cannot_list_orders(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_orders']);
        $this->actingAsUser($user);

        $this->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    public function test_orders_are_isolated_per_tenant(): void
    {
        $tenantA = $this->createTenantDatabase();
        $tenantB = $this->createTenantDatabase();

        $this->useTenantDatabase($tenantB);
        $dailyB = Daily::create(['start_date_time' => Carbon::now()]);
        Product::create(['name' => 'Tenant B Product', 'price' => 10]);
        Order::create([
            'name' => 'Tenant B Order',
            'daily_id' => $dailyB->id,
            'price' => 10,
            'status' => OrderStatus::CONFIRMED->value,
        ]);

        $userA = $this->createUser($tenantA, ['orders']);
        $this->actingAsUser($userA);

        $response = $this->getJson('/api/v1/admin/orders');
        $response->assertOk();
        $response->assertJsonMissing(['name' => 'Tenant B Order']);
    }
}
