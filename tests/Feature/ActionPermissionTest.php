<?php

namespace Tests\Feature;

use App\Enums\Device\DeviceStatusEnum;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Enums\Order\OrderStatus;
use App\Models\Daily\Daily;
use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Expense\Expense;
use App\Models\Maintenance\Maintenance;
use App\Models\Media\Media;
use App\Models\Order\Order;
use App\Models\Product\Product;
use Carbon\Carbon;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ActionPermissionTest extends TestCase
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

    public function test_user_without_action_permissions_cannot_perform_mutating_actions(): void
    {
        $tenant = $this->createTenantDatabase();
        $actor = $this->createUser($tenant, $this->readOnlyPermissions());
        $targetUser = $this->createUser($tenant, [], 'target-user@example.com');

        $this->useTenantDatabase($tenant);

        $media = Media::create(['path' => 'media/test.jpg', 'type' => 0, 'category' => 'test']);
        $daily = Daily::create(['start_date_time' => Carbon::now()]);
        $product = Product::create(['name' => 'Cola', 'price' => 15]);
        $order = Order::create([
            'name' => 'Order',
            'daily_id' => $daily->id,
            'price' => 15,
            'status' => OrderStatus::PENDING->value,
        ]);
        $expense = Expense::create([
            'name' => 'Expense',
            'price' => 10,
            'type' => ExpenseTypeEnum::EXTERNAL->value,
            'date' => now()->format('Y-m-d'),
        ]);
        $deviceType = DeviceType::create(['name' => 'PlayStation']);
        $deviceTime = DeviceTime::create([
            'device_type_id' => $deviceType->id,
            'name' => 'Hour',
            'rate' => 20,
        ]);
        $device = Device::create([
            'name' => 'PS5-1',
            'device_type_id' => $deviceType->id,
            'media_id' => $media->id,
            'status' => DeviceStatusEnum::AVAILABLE->value,
        ]);
        Maintenance::create([
            'device_id' => $device->id,
            'title' => 'Cable',
            'price' => 50,
            'date' => now()->format('Y-m-d'),
        ]);

        $this->actingAsUser($actor);

        foreach ($this->protectedActionEndpoints($targetUser->id, $media->id, $expense->id, $daily->id, $order->id, $product->id, $device->id, $deviceType->id, $deviceTime->id) as $request) {
            [$method, $uri, $payload] = $request;

            $response = $this->json($method, $uri, $payload);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Expected {$method} {$uri} to be forbidden without its action permission."
            );
        }
    }

    private function readOnlyPermissions(): array
    {
        return [
            'all_expenses',
            'all_params',
            'all_users',
            'daily',
            'devices',
            'device_times',
            'device_types',
            'edit_device',
            'edit_device_time',
            'edit_device_type',
            'edit_expense',
            'edit_media',
            'edit_order',
            'edit_product',
            'edit_user',
            'maintenances',
            'media',
            'orders',
            'products',
        ];
    }

    private function protectedActionEndpoints(
        int $userId,
        int $mediaId,
        int $expenseId,
        int $dailyId,
        int $orderId,
        int $productId,
        int $deviceId,
        int $deviceTypeId,
        int $deviceTimeId
    ): array {
        return [
            ['PUT', "/api/v1/admin/users/{$userId}", []],
            ['DELETE', "/api/v1/admin/users/{$userId}", []],
            ['POST', "/api/v1/admin/users/{$userId}/restore", []],
            ['DELETE', "/api/v1/admin/users/{$userId}/force", []],
            ['PUT', "/api/v1/admin/media/{$mediaId}", []],
            ['DELETE', "/api/v1/admin/media/{$mediaId}", []],
            ['PUT', "/api/v1/admin/expenses/{$expenseId}", []],
            ['DELETE', "/api/v1/admin/expenses/{$expenseId}", []],
            ['POST', "/api/v1/admin/expenses/{$expenseId}/restore", []],
            ['DELETE', "/api/v1/admin/expenses/{$expenseId}/force", []],
            ['PUT', "/api/v1/admin/dailies/{$dailyId}", []],
            ['DELETE', "/api/v1/admin/dailies/{$dailyId}", []],
            ['POST', '/api/v1/admin/dailies/close', ['dailyId' => $dailyId]],
            ['PUT', "/api/v1/admin/orders/{$orderId}", []],
            ['DELETE', "/api/v1/admin/orders/{$orderId}", []],
            ['POST', "/api/v1/admin/orders/{$orderId}/restore", []],
            ['DELETE', "/api/v1/admin/orders/{$orderId}/force", []],
            ['PUT', "/api/v1/admin/orders/{$orderId}/changeStatus", ['status' => OrderStatus::CONFIRMED->value]],
            ['PUT', "/api/v1/admin/orders/{$orderId}/changeTypePay", ['isPaid' => true]],
            ['PUT', "/api/v1/admin/products/{$productId}", []],
            ['DELETE', "/api/v1/admin/products/{$productId}", []],
            ['POST', "/api/v1/admin/products/{$productId}/restore", []],
            ['DELETE', "/api/v1/admin/products/{$productId}/force", []],
            ['PUT', "/api/v1/admin/devices/{$deviceId}", []],
            ['PUT', "/api/v1/admin/timers/{$deviceId}", []],
            ['DELETE', "/api/v1/admin/devices/{$deviceId}", []],
            ['POST', "/api/v1/admin/devices/{$deviceId}/restore", []],
            ['DELETE', "/api/v1/admin/devices/{$deviceId}/force", []],
            ['PUT', "/api/v1/admin/devices/{$deviceId}/changeStatus", ['status' => DeviceStatusEnum::MAINTENANCES->value]],
            ['PUT', "/api/v1/admin/device-types/{$deviceTypeId}", []],
            ['DELETE', "/api/v1/admin/device-types/{$deviceTypeId}", []],
            ['POST', "/api/v1/admin/device-types/{$deviceTypeId}/restore", []],
            ['DELETE', "/api/v1/admin/device-types/{$deviceTypeId}/force", []],
            ['PUT', "/api/v1/admin/device-times/{$deviceTimeId}", []],
            ['DELETE', "/api/v1/admin/device-times/{$deviceTimeId}", []],
            ['POST', "/api/v1/admin/device-times/{$deviceTimeId}/restore", []],
            ['DELETE', "/api/v1/admin/device-times/{$deviceTimeId}/force", []],
        ];
    }
}
