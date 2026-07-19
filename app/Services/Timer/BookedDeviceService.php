<?php
namespace App\Services\Timer;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Filters\Timer\FilterBookedDevice;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Filters\Timer\FiltersessionDeviceType;
use App\Services\Timer\Concerns\HandlesBookedDeviceActivityLog;
use App\Services\Timer\Concerns\TransfersBookedDevices;
use Illuminate\Validation\ValidationException;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Filters\Timer\FilterTypeBookedDeviceParam;

class BookedDeviceService
{
    use HandlesBookedDeviceActivityLog;
    use TransfersBookedDevices;

    public function allBookedDevices(Request $request)
    {
        $perPage = $request->query('perPage', 10);
        $bookedDevices= QueryBuilder::for(BookedDevice::class)
        ->with('sessionDevice','deviceType','deviceTime','device')
        ->when($request->trashed, function ($query) {
            $query->onlyTrashed();
        })
        ->whereHas('sessionDevice', function($q) use ($request) {
            $q->where('daily_id', $request->dailyId );
        })
        ->where('status', '!=', BookedDeviceEnum::FINISHED->value)
        ->allowedFilters([
             AllowedFilter::exact('status', 'status'),
             AllowedFilter::custom('type', new FiltersessionDeviceType),
             AllowedFilter::custom('search', new FilterBookedDevice),
             AllowedFilter::custom('bookedDevicesStatus', new FilterTypeBookedDeviceParam),
        ])
        ->cursorPaginate($perPage);
        return $bookedDevices;
    }
    public function createBookedDevice(array $data)
    {
        $alreadyBooked = BookedDevice::where('device_id', $data['deviceId'])
        ->where('device_type_id', $data['deviceTypeId'])
        ->whereIn('status', [
            BookedDeviceEnum::ACTIVE->value,
            BookedDeviceEnum::PAUSED->value
        ])
        ->exists();
        if ($alreadyBooked) {
            throw ValidationException::withMessages([
              "alreadyBooked" => __('validation.validation_create_booked_device')
              ]);
        }
        return BookedDevice::create([
        'session_device_id'=>$data['sessionDeviceId'],
        'device_type_id'=>$data['deviceTypeId'],
        'device_id'=>$data['deviceId'],
        'device_time_id'=>$data['deviceTimeId'],
        'start_date_time'=>$data['startDateTime'],
        'total_used_seconds'=>$data['totalUsedSeconds']??0,
        'end_date_time'=>$data['endDateTime']??null,
        'status'=>$data['status'],
        ]);
    }

    public function editBookedDevice(int $id)
    {
        return BookedDevice::with('sessionDevice','deviceType','deviceTime','device')->findOrFail($id);
    }

    public function updateBookedDevice(int $id, array $data)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        $bookedDevice->update($data);
        return $bookedDevice;
    }

    public function finishBookedDevice(int $id, array $data = [])
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status == BookedDeviceEnum::FINISHED->value)
        {
            throw new Exception("the booked device Finished status");
        }
        $start = $bookedDevice->start_date_time;
        $end = Carbon::now();
        if($bookedDevice->end_date_time && $bookedDevice->end_date_time->lessThan($end)) {
            $end = $bookedDevice->end_date_time;
        }
        $total = $start->diffInSeconds($end);
        $used = max(0, $total - (int) $bookedDevice->total_paused_seconds);
        $bookedDevice->update([
            'end_date_time' => $end,
            'total_used_seconds' => $used,
            'status' => BookedDeviceEnum::FINISHED->value
        ]);
        $bookedDevice->period_cost=$bookedDevice->calculatePrice();
        $bookedDevice->actual_paid_amount = $data['actualPaidAmount'] ?? $bookedDevice->total_cost;
        $bookedDevice->save();

        $bookedDevice->orders->each(function ($order) {
            $order->update([
                'is_paid' => 1,
            ]);
        });


        return $bookedDevice->fresh();
    }
    public function deleteBookedDevice(int $id)
    {
        $bookedDevice = BookedDevice::findOrFail($id);
        $sessionDevice = $bookedDevice->sessionDevice()->withTrashed()->first();

        if ($sessionDevice && $sessionDevice->type == SessionDeviceEnum::GROUP->value) {
            if ($sessionDevice->bookedDevices()->count() == 1) {
                // Last device in group session - delete session with logging
                $this->deleteSessionWithLogging($sessionDevice, [$bookedDevice]);
            }
            if ($bookedDevice->pauses()->count() > 0) {
                $bookedDevice->pauses()->delete();
            }
            BookedDevice::where('session_device_id', $sessionDevice->id)
                ->where('device_id', $bookedDevice->device_id)
                ->where('device_type_id', $bookedDevice->device_type_id)
                ->delete();
        } else {
            // Individual session - delete device and session with logging
            if ($bookedDevice->pauses()->count() > 0) {
                $bookedDevice->pauses()->delete();
            }

            BookedDevice::where('session_device_id', $sessionDevice->id)
                ->where('device_id', $bookedDevice->device_id)
                ->where('device_type_id', $bookedDevice->device_type_id)
                ->delete();

            if ($sessionDevice) {
                // Delete session with logging including device details
                $this->deleteSessionWithLogging($sessionDevice, [$bookedDevice]);
            }
        }
    }

    private function deleteSessionWithLogging($sessionDevice, $bookedDevices)
    {
        // Prepare children data
        $children = collect($bookedDevices)->map(function ($device) {
            return [
                'id' => $device->id,
                'event' => 'deleted',
                'log_name' => 'BookedDevice',
                'device_id' => $device->device_id,
                'device_type_id' => $device->device_type_id,
                'device_time_id' => $device->device_time_id,
                'status' => $device->status,
            ];
        })->toArray();

        // Delete without triggering automatic events
        $sessionDevice->withoutEvents(function () use ($sessionDevice) {
            $sessionDevice->delete();
        });

        // Log the deletion manually with children
        activity()
            ->useLog('SessionDevice')
            ->event('deleted')
            ->performedOn($sessionDevice)
            ->causedBy(auth()->user())
            ->withProperties([
                'old' => [
                    'id' => $sessionDevice->id,
                    'name' => $sessionDevice->name,
                    'type' => $sessionDevice->type,
                ],
                'children' => $children,
                'session_key' => $sessionDevice->type === SessionDeviceEnum::INDIVIDUAL->value
                    ? 'individual_' . ($bookedDevices[0]->device_id ?? 'unknown') . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s')
                    : 'session_' . $sessionDevice->id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s'), // Add session key
                'delete_type' => 'session_with_devices' // Mark delete type
            ])
            ->tap(function ($activity) use ($sessionDevice) {
                $activity->daily_id = $sessionDevice->daily_id;
            })
            ->log('SessionDevice deleted');
    }

    public function restoreBookedDevice(int $id)
    {
        $bookedDevice = BookedDevice::onlyTrashed()->findOrFail($id);

        if ($bookedDevice->sessionDevice()->withTrashed()->exists()) {
            $bookedDevice->sessionDevice()->withTrashed()->first()->restore();
        }
        $bookedDevice->restore();

        return $bookedDevice;
    }

    public function forceDeleteBookedDevice(int $id)
    {
        $bookedDevice = BookedDevice::withTrashed()->findOrFail($id);
        if ($bookedDevice->pauses()->withTrashed()->count() > 0) {
            $bookedDevice->pauses()->withTrashed()->forceDelete();
        }
        $bookedDevice->forceDelete();
    }
    public function updateEndDateTime(int $id, array $data)
        {
            return DB::transaction(function () use ($id, $data) {
            $bookedDevice = BookedDevice::findOrFail($id);

            if ($bookedDevice->status == BookedDeviceEnum::FINISHED->value) {
                throw new Exception("the booked device Finished status");
            }

            // Save old values for logging (keep as Carbon object or null)
            $oldEndDateTime = $bookedDevice->end_date_time;

            // Update fields without triggering events
            $bookedDevice->withoutEvents(function () use ($bookedDevice, $data) {
                if (empty($data['endDateTime'])) {
                    $bookedDevice->end_date_time = null;
                } else {
                    $bookedDevice->end_date_time = Carbon::parse($data['endDateTime']);
                }

                $bookedDevice->total_used_seconds = 0;
                $bookedDevice->period_cost = 0;
                $bookedDevice->save();
            });

            // Refresh to get updated values
            $bookedDevice->refresh();

            // Get session device
            $sessionDevice = $bookedDevice->sessionDevice;

            if ($sessionDevice) {
                // Generate session key
                $sessionKey = $sessionDevice->type === SessionDeviceEnum::INDIVIDUAL->value
                    ? 'individual_' . $bookedDevice->device_id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s')
                    : 'session_' . $sessionDevice->id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s');

                // Manual activity log for SessionDevice with BookedDevice as child
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
                            'name' => $sessionDevice->name,
                            'type' => $sessionDevice->type,
                        ],
                        'children' => [[
                            'id' => $bookedDevice->id,
                            'event' => 'updated',
                            'log_name' => 'BookedDevice',
                            'device_id' => $bookedDevice->device_id,
                            'device_type_id' => $bookedDevice->device_type_id,
                            'device_time_id' => $bookedDevice->device_time_id,
                            'status' => $bookedDevice->status,
                            'end_date_time' => $bookedDevice->end_date_time ? $bookedDevice->end_date_time->format('Y-m-d H:i:s') : null,
                            'old_end_date_time' => $oldEndDateTime ? $oldEndDateTime->format('Y-m-d H:i:s') : null,
                        ]],
                        'session_key' => $sessionKey, // Add session key
                        'update_type' => 'end_time' // Mark update type
                    ])
                    ->tap(function ($activity) use ($sessionDevice) {
                        $activity->daily_id = $sessionDevice->daily_id;
                    })
                    ->log('SessionDevice - BookedDevice end time updated');
            }

            // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();

            return $bookedDevice;
            });
        }

    public function createBookedDeviceWithoutLog(array $data)
    {
        $alreadyBooked = BookedDevice::where('device_id', $data['deviceId'])
        ->where('device_type_id', $data['deviceTypeId'])
        ->whereIn('status', [
            BookedDeviceEnum::ACTIVE->value,
            BookedDeviceEnum::PAUSED->value
        ])
        ->exists();

        if ($alreadyBooked) {
            throw ValidationException::withMessages([
              "alreadyBooked" => __('validation.validation_create_booked_device')
              ]);
        }

        // Create without triggering events/logging
        $bookedDevice = new BookedDevice([
            'session_device_id'=>$data['sessionDeviceId'],
            'device_type_id'=>$data['deviceTypeId'],
            'device_id'=>$data['deviceId'],
            'device_time_id'=>$data['deviceTimeId'],
            'start_date_time'=>$data['startDateTime'],
            'total_used_seconds'=>$data['totalUsedSeconds']??0,
            'end_date_time'=>$data['endDateTime']??null,
            'status'=>$data['status'],
        ]);

        $bookedDevice->saveQuietly();

        return $bookedDevice;
    }

}
