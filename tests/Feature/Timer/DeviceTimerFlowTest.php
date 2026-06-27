<?php

namespace Tests\Feature\Timer;

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
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeviceTimerFlowTest extends TestCase
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

    public function test_can_start_individual_timer_via_api(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_devices']);
        $now = Carbon::parse('2026-06-15 10:00:00');

        $this->useTenantDatabase($tenant);
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
        $daily = Daily::create(['start_date_time' => $now]);

        $this->actingAsUser($user);
        Carbon::setTestNow($now);

        $this->postJson('/api/v1/admin/device-timer/individual-time', [
            'deviceId' => $device->id,
            'deviceTypeId' => $deviceType->id,
            'deviceTimeId' => $deviceTime->id,
            'startDateTime' => $now->toDateTimeString(),
            'endDateTime' => $now->copy()->addHour()->toDateTimeString(),
            'dailyId' => $daily->id,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('booked_devices', [
            'device_id' => $device->id,
            'status' => BookedDeviceEnum::ACTIVE->value,
        ], 'tenant');
    }

    public function test_pause_and_resume_timer(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['update_device']);
        $now = Carbon::parse('2026-06-15 12:00:00');

        $bookedDevice = $this->seedActiveTimer($tenant, $now);

        $this->actingAsUser($user);
        Carbon::setTestNow($now);

        $this->postJson("/api/v1/admin/device-timer/{$bookedDevice->id}/pause")
            ->assertOk();

        $bookedDevice->refresh();
        $this->assertSame(BookedDeviceEnum::PAUSED->value, $bookedDevice->status);

        $this->postJson("/api/v1/admin/device-timer/{$bookedDevice->id}/resume")
            ->assertOk();

        $bookedDevice->refresh();
        $this->assertSame(BookedDeviceEnum::ACTIVE->value, $bookedDevice->status);
    }

    public function test_cannot_pause_already_finished_timer(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['update_device']);
        $now = Carbon::parse('2026-06-15 12:00:00');

        $bookedDevice = $this->seedActiveTimer($tenant, $now);
        $bookedDevice->update(['status' => BookedDeviceEnum::FINISHED->value]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/admin/device-timer/{$bookedDevice->id}/pause")
            ->assertStatus(500);
    }

    private function seedActiveTimer(string $tenant, Carbon $now): BookedDevice
    {
        $this->useTenantDatabase($tenant);

        $deviceType = DeviceType::create(['name' => 'PS']);
        $device = Device::create([
            'name' => 'PS1',
            'device_type_id' => $deviceType->id,
            'status' => DeviceStatusEnum::UNAVAILABLE->value,
        ]);
        $deviceTime = DeviceTime::create([
            'name' => 'Hour',
            'rate' => 50,
            'device_type_id' => $deviceType->id,
        ]);
        $daily = Daily::create(['start_date_time' => $now]);
        $session = SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id,
        ]);

        return BookedDevice::create([
            'session_device_id' => $session->id,
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'device_time_id' => $deviceTime->id,
            'start_date_time' => $now->copy()->subHour(),
            'status' => BookedDeviceEnum::ACTIVE->value,
        ]);
    }
}
