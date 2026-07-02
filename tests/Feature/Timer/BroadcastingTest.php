<?php

namespace Tests\Feature\Timer;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\Device\DeviceStatusEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Events\BookedDeviceChangeStatus;
use App\Models\Daily\Daily;
use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BroadcastingTest extends TestCase
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

    public function test_finishing_timer_dispatches_booked_device_change_status_event(): void
    {
        Event::fake([BookedDeviceChangeStatus::class]);

        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['update_device']);
        $now = Carbon::parse('2026-06-15 12:00:00');

        $bookedDevice = $this->seedActiveTimer($tenant, $now);

        Carbon::setTestNow($now);
        $this->actingAsUser($user);

        $this->postJson("/api/v1/admin/device-timer/{$bookedDevice->id}/finish", [
            'actualPaidAmount' => 50,
        ])->assertOk();

        Event::assertDispatched(BookedDeviceChangeStatus::class, function ($event) use ($bookedDevice) {
            return $event->bookedDevice->id === $bookedDevice->id
                && $event->broadcastAs() === 'booked-device-change-status'
                && $event->broadcastOn()[0]->name === 'booked-devices'
                && $event->broadcastWith()['status'] === BookedDeviceEnum::FINISHED->value;
        });
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
        $daily = Daily::create(['start_date_time' => $now->copy()->startOfDay()]);
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
