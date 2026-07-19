<?php

namespace App\Services\Timer\Concerns;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use Exception;
use Illuminate\Support\Facades\DB;

trait TransfersBookedDevices
{
    public function transferDeviceToGroup(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
        $bookedDevice = BookedDevice::findOrFail($id);
        $oldSessionDevice = $bookedDevice->sessionDevice()->withTrashed()->first();

        if ($data['sessionDeviceId'] ?? null) {

            $sessionDevice = SessionDevice::findOrFail($data['sessionDeviceId']);

            if ($sessionDevice->type === SessionDeviceEnum::INDIVIDUAL->value) {
                throw new Exception("The session device type must be group.");
            }
        } elseif ($data['name'] ?? null) {
            $currentSession = $bookedDevice->sessionDevice()->withTrashed()->first();
            $sessionDevice = SessionDevice::withoutEvents(function () use ($data, $currentSession) {
                return SessionDevice::create([
                    'name' => $data['name'],
                    'type' => SessionDeviceEnum::GROUP->value,
                    'daily_id' => $currentSession ? $currentSession->daily_id : ($data['dailyId'] ?? null),
                ]);
            });
            $data['sessionDeviceId'] = $sessionDevice->id;
        }

        if ($bookedDevice->status === BookedDeviceEnum::FINISHED->value) {
            throw new Exception("The booked device has a finished status.");
        }

        if ($bookedDevice->session_device_id === $data['sessionDeviceId']) {
            throw new Exception("The booked device is already in this session device.");
        }

        $updated = BookedDevice::where('session_device_id', $bookedDevice->session_device_id)
        ->where('device_id', $bookedDevice->device_id)
        ->where('device_type_id', $bookedDevice->device_type_id)
        // ->where('status', '!=', BookedDeviceEnum::FINISHED->value)
        ->update([
        'session_device_id' => $data['sessionDeviceId'],
        ]);

        // Log the transfer with BookedDevice as child
        $targetSession = SessionDevice::find($data['sessionDeviceId']);
        if ($targetSession) {
            // Generate session key for the new session
            $newSessionKey = 'session_' . $targetSession->id . '_' . $targetSession->created_at->format('Y-m-d_H-i-s');

            activity()
                ->useLog('SessionDevice')
                ->event('transfer')
                ->performedOn($targetSession)
                ->causedBy(auth()->user())
                ->withProperties([
                    'attributes' => [
                        'id' => $targetSession->id,
                        'name' => $targetSession->name,
                        'type' => $targetSession->type,
                    ],
                    'old' => [
                        'name' => $oldSessionDevice ? $oldSessionDevice->name : '',
                        'type' => $targetSession->type,
                    ],
                    'children' => [
                        [
                            'id' => $bookedDevice->id,
                            'event' => 'transfer',
                            'log_name' => 'BookedDevice',
                            'device_id' => $bookedDevice->device_id,
                            'device_type_id' => $bookedDevice->device_type_id,
                            'device_time_id' => $bookedDevice->device_time_id,
                            'status' => $bookedDevice->status,
                        ]
                    ],
                    'session_key' => $newSessionKey, // Add session key
                    'timer_id' => $this->getTimerId($bookedDevice), // Add timer lifecycle ID
                    'transfer_type' => 'to_group' // Mark transfer type
                ])
                ->tap(function ($activity) use ($targetSession) {
                    $activity->daily_id = $targetSession->daily_id;
                })
                ->log('Device transferred');
        }

        //delete any session device if no booked devices left (without triggering events)
        // DON'T delete the old session - keep it for activity log history
        if ($oldSessionDevice && $oldSessionDevice->bookedDevices()->count() == 0) {
            // Don't delete - just leave it as is for history
            // $oldSessionDevice->withoutEvents(function () use ($oldSessionDevice) {
            //     $oldSessionDevice->delete();
            // });
        }

        // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();
        return $updated;
        });
    }
    public function transferBookedDeviceToSessionDevice(int $bookedDeviceId ,$dailyId)
    {
        return DB::transaction(function () use ($bookedDeviceId, $dailyId) {
        $bookedDevice = BookedDevice::findOrFail($bookedDeviceId);
        $currentSession = $bookedDevice->sessionDevice()->withTrashed()->first();
        if ($currentSession && $currentSession->type === SessionDeviceEnum::INDIVIDUAL->value) {
            throw new Exception("The session device type must be group.");
        }
        if ($bookedDevice->status === BookedDeviceEnum::FINISHED->value) {
            throw new Exception("The booked device has a finished status.");
        }

        // Get old session name and ID for logging
        $oldSessionName = $currentSession ? $currentSession->name : '';
        $oldSessionId = $bookedDevice->session_device_id;

        // Create new session without triggering automatic activity log
        $newSessionDevice = SessionDevice::withoutEvents(function () use ($dailyId) {
            return SessionDevice::create([
                'name' =>'individual',
                'type' => SessionDeviceEnum::INDIVIDUAL->value,
                'daily_id' => $dailyId,
            ]);
        });

        //
        $updated = BookedDevice::where('session_device_id', $bookedDevice->session_device_id)
        ->where('device_id', $bookedDevice->device_id)
        ->where('device_type_id', $bookedDevice->device_type_id)
        ->update([
        'session_device_id' => $newSessionDevice->id,
        ]);

        // Log the transfer with BookedDevice as child
        activity()
            ->useLog('SessionDevice')
            ->event('transfer')
            ->performedOn($newSessionDevice)
            ->causedBy(auth()->user())
            ->withProperties([
                'attributes' => [
                    'id' => $newSessionDevice->id,
                    'name' => $newSessionDevice->name,
                    'type' => $newSessionDevice->type,
                ],
                'old' => [
                    'name' => $oldSessionName,  // Old session name in old.name
                    'type' => $newSessionDevice->type,
                ],
                'children' => [
                    [
                        'id' => $bookedDevice->id,
                        'event' => 'transfer',
                        'log_name' => 'BookedDevice',
                        'device_id' => $bookedDevice->device_id,
                        'device_type_id' => $bookedDevice->device_type_id,
                        'device_time_id' => $bookedDevice->device_time_id,
                        'status' => $bookedDevice->status,
                    ]
                ],
                'session_key' => 'individual_' . $bookedDevice->device_id . '_' . $newSessionDevice->created_at->format('Y-m-d_H-i-s'), // Add session key
                'timer_id' => $this->getTimerId($bookedDevice), // Add timer lifecycle ID
                'transfer_type' => 'to_individual' // Mark transfer type
            ])
            ->tap(function ($activity) use ($dailyId) {
                $activity->daily_id = $dailyId;
            })
            ->log('SessionDevice transfer');

        //delete any session device if no booked devices left (without triggering events)
        // DON'T delete the old session - keep it for activity log history
        // The soft-deleted sessions will still be accessible through withTrashed()
        $oldSessionDevice = SessionDevice::withTrashed()->find($oldSessionId);
        if ($oldSessionDevice && $oldSessionDevice->bookedDevices()->count() == 0) {
            // Don't delete - just leave it as is for history
            // $oldSessionDevice->withoutEvents(function () use ($oldSessionDevice) {
            //     $oldSessionDevice->delete();
            // });
        }
        return $updated;
        });
    }
}
