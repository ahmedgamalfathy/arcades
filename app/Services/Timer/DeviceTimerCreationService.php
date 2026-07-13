<?php

namespace App\Services\Timer;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Timer\SessionDevice\SessionDevice;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceTimerCreationService
{
    public function __construct(
        private BookedDeviceService $bookedDeviceService,
        private SessionDeviceService $sessionDeviceService
    ) {
    }

    public function createIndividual(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $sessionDevice = SessionDevice::withoutEvents(function () use ($data) {
                return SessionDevice::create([
                    'name' => 'individual',
                    'type' => SessionDeviceEnum::INDIVIDUAL->value,
                    'daily_id' => $data['dailyId'],
                ]);
            });

            $data = $this->prepareTimerData($data);
            $data['sessionDeviceId'] = $sessionDevice->id;

            $device = $this->bookedDeviceService->createBookedDeviceWithoutLog($data);

            $this->logSessionCreated(
                $sessionDevice,
                $device,
                $data['dailyId'],
                'individual',
                'SessionDevice - Individual time created'
            );
        });
    }

    public function createGroup(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $name = $data['name'] ?? null;
            $sessionDeviceId = $data['sessionDeviceId'] ?? null;

            if ($name && $sessionDeviceId) {
                throw new Exception('name and sessionDeviceId are required');
            }

            if ($name) {
                $sessionDevice = SessionDevice::withoutEvents(function () use ($data, $name) {
                    return SessionDevice::create([
                        'name' => $name,
                        'type' => SessionDeviceEnum::GROUP->value,
                        'daily_id' => $data['dailyId'],
                    ]);
                });
                $data['sessionDeviceId'] = $sessionDevice->id;
                $isNewSession = true;
            } else {
                $sessionDevice = $this->sessionDeviceService->editSessionDevice($sessionDeviceId);
                $data['sessionDeviceId'] = $sessionDevice->id;
                $isNewSession = false;
            }

            $data = $this->prepareTimerData($data);
            $device = $this->bookedDeviceService->createBookedDeviceWithoutLog($data);

            if ($isNewSession) {
                $this->logSessionCreated(
                    $sessionDevice,
                    $device,
                    $data['dailyId'],
                    'group',
                    'SessionDevice - Group time created'
                );

                return;
            }

            $this->logDeviceAddedToGroup($sessionDevice, $device, $data['dailyId']);
        });
    }

    private function prepareTimerData(array $data): array
    {
        $start = Carbon::parse($data['startDateTime']);
        $end = $data['endDateTime'] ? Carbon::parse($data['endDateTime']) : null;

        if ($end && $end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'endDateTime' => 'The end time must be after the start time.',
            ]);
        }

        $data['startDateTime'] = $start;
        $data['endDateTime'] = $end;
        $data['status'] = BookedDeviceEnum::ACTIVE->value;

        if ($end) {
            $data['totalUsedSeconds'] = $start->diffInSeconds($end);
        }

        return $data;
    }

    private function logSessionCreated(
        SessionDevice $sessionDevice,
        object $device,
        int $dailyId,
        string $sessionType,
        string $message
    ): void {
        activity()
            ->useLog('SessionDevice')
            ->event('created')
            ->performedOn($sessionDevice)
            ->withProperties([
                'attributes' => [
                    'id' => $sessionDevice->id,
                    'name' => $sessionDevice->name,
                    'type' => $sessionDevice->type,
                ],
                'old' => [
                    'name' => '',
                    'type' => '',
                ],
                'children' => [$this->bookedDeviceChild($device, 'created')],
                'device_session_key' => $this->deviceSessionKey($device, $dailyId),
                'timer_id' => $this->timerId($device),
                'session_type' => $sessionType,
            ])
            ->tap(function ($activity) use ($sessionDevice) {
                $activity->daily_id = $sessionDevice->daily_id;
            })
            ->log($message);
    }

    private function logDeviceAddedToGroup(SessionDevice $sessionDevice, object $device, int $dailyId): void
    {
        activity()
            ->useLog('SessionDevice')
            ->event('updated')
            ->performedOn($sessionDevice)
            ->withProperties([
                'attributes' => [
                    'id' => $sessionDevice->id,
                    'name' => $sessionDevice->name,
                    'type' => $sessionDevice->type,
                ],
                'old' => [
                    'id' => $sessionDevice->id,
                    'name' => $sessionDevice->name,
                    'type' => $sessionDevice->type,
                ],
                'children' => [$this->bookedDeviceChild($device, 'created')],
                'device_session_key' => $this->deviceSessionKey($device, $dailyId),
                'timer_id' => $this->timerId($device),
                'session_type' => 'group',
                'action_type' => 'add_device',
            ])
            ->tap(function ($activity) use ($sessionDevice) {
                $activity->daily_id = $sessionDevice->daily_id;
            })
            ->log('SessionDevice - New device added to group');
    }

    private function bookedDeviceChild(object $device, string $event): array
    {
        return [
            'id' => $device->id,
            'event' => $event,
            'log_name' => 'BookedDevice',
            'device_id' => $device->device_id,
            'device_type_id' => $device->device_type_id,
            'device_time_id' => $device->device_time_id,
            'status' => $device->status,
        ];
    }

    private function deviceSessionKey(object $device, int $dailyId): string
    {
        return 'device_'.$device->device_id.'_daily_'.$dailyId.'_'.now()->format('Y-m-d');
    }

    private function timerId(object $device): string
    {
        return 'timer_'.$device->device_id.'_'.$device->created_at->timestamp;
    }
}
