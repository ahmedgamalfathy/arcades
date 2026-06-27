<?php

namespace Tests\Feature\Daily;

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

class DailyTest extends TestCase
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

    public function test_user_can_open_daily_shift(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_daily']);
        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/dailies', [
            'startDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('dailies', 1, 'tenant');
    }

    public function test_cannot_open_second_daily_while_one_is_open(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_daily']);
        $this->useTenantDatabase($tenant);

        Daily::create(['start_date_time' => Carbon::now()]);
        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/dailies', [
            'startDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
        ])
            ->assertStatus(500);
    }

    public function test_cannot_close_daily_with_active_timer(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['close_daily']);
        $this->useTenantDatabase($tenant);

        $daily = Daily::create(['start_date_time' => Carbon::now()]);
        $session = SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id,
        ]);
        $deviceType = DeviceType::create(['name' => 'PS']);
        $deviceTime = DeviceTime::create([
            'name' => 'Hour',
            'rate' => 50,
            'device_type_id' => $deviceType->id,
        ]);
        BookedDevice::create([
            'session_device_id' => $session->id,
            'device_type_id' => $deviceType->id,
            'device_id' => Device::create([
                'name' => 'PS1',
                'device_type_id' => $deviceType->id,
                'status' => DeviceStatusEnum::AVAILABLE->value,
            ])->id,
            'device_time_id' => $deviceTime->id,
            'status' => BookedDeviceEnum::ACTIVE->value,
            'start_date_time' => Carbon::now(),
        ]);

        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/dailies/close', ['dailyId' => $daily->id])
            ->assertUnprocessable();
    }

    public function test_can_close_daily_when_all_timers_finished(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['close_daily']);
        $this->useTenantDatabase($tenant);

        $daily = Daily::create(['start_date_time' => Carbon::now()]);
        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/dailies/close', ['dailyId' => $daily->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $daily->refresh();
        $this->assertNotNull($daily->end_date_time);
    }
}
