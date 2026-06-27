<?php

namespace Tests\Feature\Report;

use App\Enums\Order\OrderStatus;
use App\Models\Daily\Daily;
use App\Models\Product\Product;
use Carbon\Carbon;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_report_returns_stats_for_date_range(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['view_reports']);
        $this->useTenantDatabase($tenant);

        $date = Carbon::parse('2026-06-15');
        Daily::create([
            'start_date_time' => $date->copy()->startOfDay(),
            'end_date_time' => $date->copy()->endOfDay(),
            'total_income' => 500,
            'total_expense' => 100,
            'total_profit' => 400,
        ]);

        $this->actingAsUser($user);

        $this->getJson('/api/v1/admin/reports?startDateTime=2026-06-15&endDateTime=2026-06-15')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['stats', 'mostRequested', 'mostUsedBookedDevice', 'percentage'],
            ]);
    }

    public function test_status_report_returns_dailies_in_range(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['view_reports']);
        $this->useTenantDatabase($tenant);

        Daily::create(['start_date_time' => Carbon::parse('2026-06-15 08:00:00')]);
        $this->actingAsUser($user);

        $this->getJson('/api/v1/admin/reports/getStatusReport?startDateTime=2026-06-15&endDateTime=2026-06-15')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_report_reflects_order_income(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['view_reports', 'create_orders']);
        $this->useTenantDatabase($tenant);

        $daily = Daily::create(['start_date_time' => Carbon::parse('2026-06-15 08:00:00')]);
        $product = Product::create(['name' => 'Snack', 'price' => 25]);

        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/orders', [
            'name' => 'Cafe Order',
            'dailyId' => $daily->id,
            'isPaid' => true,
            'status' => OrderStatus::CONFIRMED->value,
            'orderItems' => [['productId' => $product->id, 'qty' => 1]],
        ])->assertOk();

        $this->getJson('/api/v1/admin/reports?startDateTime=2026-06-15&endDateTime=2026-06-15&include=orders')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
