<?php

namespace Tests\Feature;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\Device\DeviceStatusEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Daily\Daily;
use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeviceTimerCalculationTest extends TestCase
{
    use InteractsWithTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantTesting();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownTenantTesting();
        parent::tearDown();
    }

    public function test_session_cost_is_calculated_and_device_becomes_available_when_timer_finishes(): void
    {
        $tenantDatabase = $this->createTenantDatabase();
        $user = $this->createUser($tenantDatabase, ['update_device']);
        $now = Carbon::parse('2026-06-15 12:00:00');

        $this->useTenantDatabase($tenantDatabase);

        $deviceType = DeviceType::create(['name' => 'PlayStation']);
        $device = Device::create([
            'name' => 'PS5-01',
            'device_type_id' => $deviceType->id,
            'status' => DeviceStatusEnum::UNAVAILABLE->value,
        ]);
        $deviceTime = DeviceTime::create([
            'name' => 'Hourly',
            'rate' => 50,
            'device_type_id' => $deviceType->id,
        ]);
        $daily = Daily::create(['start_date_time' => $now->copy()->startOfDay()]);
        $session = SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id,
        ]);
        $bookedDevice = BookedDevice::create([
            'session_device_id' => $session->id,
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'device_time_id' => $deviceTime->id,
            'start_date_time' => $now->copy()->subHours(2),
            'status' => BookedDeviceEnum::ACTIVE->value,
        ]);

        DB::setDefaultConnection('mysql');
        Carbon::setTestNow($now);
        $this->actingAsUser($user);

        $this->postJson("/api/v1/admin/device-timer/{$bookedDevice->id}/finish")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price', 100);

        $finished = BookedDevice::on('tenant')->findOrFail($bookedDevice->id);
        $device->refresh();

        $this->assertSame(BookedDeviceEnum::FINISHED->value, $finished->status);
        $this->assertSame(7200, (int) $finished->total_used_seconds);
        $this->assertEquals(100.00, (float) $finished->period_cost);
        $this->assertSame(DeviceStatusEnum::AVAILABLE->value, $device->status);
    }
}
