<?php

namespace Tests\Feature\Report;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\Device\DeviceStatusEnum;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\OrderTypeEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Daily\Daily;
use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Expense\Expense;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use Carbon\Carbon;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinancialReportEdgeCaseTest extends TestCase
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

    public function test_empty_report_range_returns_zero_stats_and_percentages(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['view_reports']);

        $this->actingAsUser($user);

        $this->getJson('/api/v1/admin/reports?startDateTime=2026-06-15&endDateTime=2026-06-15')
            ->assertOk()
            ->assertJsonPath('data.stats.totalIncome', 0)
            ->assertJsonPath('data.stats.totalOrders', 0)
            ->assertJsonPath('data.stats.totalSessions', 0)
            ->assertJsonPath('data.stats.totalExpense', 0)
            ->assertJsonPath('data.stats.totalProfit', 0)
            ->assertJsonPath('data.percentage.orderPercentage', 0)
            ->assertJsonPath('data.percentage.sessionPercentage', 0)
            ->assertJsonPath('data.percentage.expensePercentage', 0);
    }

    public function test_report_calculates_orders_sessions_expenses_and_percentages(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['view_reports']);
        $this->useTenantDatabase($tenant);

        $daily = Daily::create(['start_date_time' => Carbon::parse('2026-06-15 08:00:00')]);
        $product = Product::create(['name' => 'Cola', 'price' => 30]);
        $order = Order::create([
            'name' => 'Cafe Order',
            'daily_id' => $daily->id,
            'price' => 60,
            'status' => OrderStatus::CONFIRMED->value,
            'type' => OrderTypeEnum::EXTERNAL->value,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'price' => 30,
            'qty' => 2,
        ]);
        Expense::create([
            'name' => 'Supplies',
            'type' => ExpenseTypeEnum::INTERNAL->value,
            'price' => 20,
            'date' => '2026-06-15',
            'daily_id' => $daily->id,
        ]);

        $deviceType = DeviceType::create(['name' => 'PS']);
        $device = Device::create([
            'name' => 'PS1',
            'device_type_id' => $deviceType->id,
            'status' => DeviceStatusEnum::AVAILABLE->value,
        ]);
        $deviceTime = DeviceTime::create([
            'name' => 'Hour',
            'rate' => 50,
            'device_type_id' => $deviceType->id,
        ]);
        $session = SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id,
        ]);
        BookedDevice::create([
            'session_device_id' => $session->id,
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'device_time_id' => $deviceTime->id,
            'start_date_time' => Carbon::parse('2026-06-15 09:00:00'),
            'end_date_time' => Carbon::parse('2026-06-15 10:00:00'),
            'status' => BookedDeviceEnum::FINISHED->value,
            'period_cost' => 50,
            'actual_paid_amount' => 50,
        ]);

        $this->actingAsUser($user);

        $this->getJson('/api/v1/admin/reports?startDateTime=2026-06-15&endDateTime=2026-06-15&include=orders,sessions,expenses')
            ->assertOk()
            ->assertJsonPath('data.stats.totalIncome', 110)
            ->assertJsonPath('data.stats.totalOrders', 60)
            ->assertJsonPath('data.stats.totalSessions', 50)
            ->assertJsonPath('data.stats.totalExpense', 20)
            ->assertJsonPath('data.stats.totalProfit', 90)
            ->assertJsonPath('data.percentage.orderPercentage', 46.15)
            ->assertJsonPath('data.percentage.sessionPercentage', 38.46)
            ->assertJsonPath('data.percentage.expensePercentage', 15.38)
            ->assertJsonPath('data.mostRequested.0.productName', 'Cola')
            ->assertJsonPath('data.mostRequested.0.totalQuantity', 2)
            ->assertJsonPath('data.mostUsedBookedDevice.0.deviceName', 'PS1')
            ->assertJsonPath('data.mostUsedBookedDevice.0.totalHours', 1);
    }
}
