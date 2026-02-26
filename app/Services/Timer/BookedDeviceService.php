<?php
namespace App\Services\Timer;
use Exception;
use Carbon\Carbon;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use App\Models\Order\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\Activitylog\Models\Activity;
use App\Events\BookedDeviceChangeStatus;
use App\Filters\Timer\FilterBookedDevice;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Filters\Timer\FiltersessionDeviceType;
use Illuminate\Validation\ValidationException;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Filters\Timer\FilterTypeBookedDeviceParam;
use App\Models\Timer\BookedDevicePause\BookedDevicePause;

use function PHPUnit\Framework\isEmpty;

class BookedDeviceService
{
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
                $sessionDevice->delete();
            }
            if ($bookedDevice->pauses()->count() > 0) {
                $bookedDevice->pauses()->delete();
            }
            BookedDevice::where('session_device_id', $sessionDevice->id)
                ->where('device_id', $bookedDevice->device_id)
                ->where('device_type_id', $bookedDevice->device_type_id)
                ->delete();
        } else {
            /*
            $bookedDevice->delete();
            */
            BookedDevice::where('session_device_id', $sessionDevice->id)
                ->where('device_id', $bookedDevice->device_id)
                ->where('device_type_id', $bookedDevice->device_type_id)
                ->delete();
            if ($sessionDevice) {
                $sessionDevice->delete();
            }
        }
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
                    ])
                    ->tap(function ($activity) use ($sessionDevice) {
                        $activity->daily_id = $sessionDevice->daily_id;
                    })
                    ->log('SessionDevice - BookedDevice end time updated');
            }

            // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();

            return $bookedDevice;
        }

    public function transferDeviceToGroup(int $id, array $data)
    {
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

        // Log the transfer as an update to the target session with BookedDevice as child
        $targetSession = SessionDevice::find($data['sessionDeviceId']);
        if ($targetSession) {
            activity()
                ->useLog('SessionDevice')
                ->event('updated')
                ->performedOn($targetSession)
                ->causedBy(auth('api')->user())
                ->withProperties([
                    'attributes' => [
                        'id' => $targetSession->id,
                        'name' => $targetSession->name,
                        'type' => $targetSession->type,
                    ],
                    'old' => [
                        'name' => $targetSession->name,
                        'type' => $targetSession->type,
                    ],
                    'children' => [
                        [
                            'id' => $bookedDevice->id,
                            'event' => 'updated',
                            'log_name' => 'BookedDevice',
                            'device_id' => $bookedDevice->device_id,
                            'device_type_id' => $bookedDevice->device_type_id,
                            'device_time_id' => $bookedDevice->device_time_id,
                            'status' => $bookedDevice->status,
                        ]
                    ]
                ])
                ->tap(function ($activity) use ($targetSession) {
                    $activity->daily_id = $targetSession->daily_id;
                })
                ->log('Device transferred to group');
        }

        //delete any session device if no booked devices left (without triggering events)
        if ($oldSessionDevice && $oldSessionDevice->bookedDevices()->count() == 0) {
            $oldSessionDevice->withoutEvents(function () use ($oldSessionDevice) {
                $oldSessionDevice->delete();
            });
        }

        // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();
        return $updated;
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
            ->event('created')
            ->performedOn($newSessionDevice)
            ->causedBy(auth('api')->user())
            ->withProperties([
                'attributes' => [
                    'id' => $newSessionDevice->id,
                    'name' => $newSessionDevice->name,
                    'type' => $newSessionDevice->type,
                ],
                'old' => [
                    'name' => $newSessionDevice->name,
                    'type' => $newSessionDevice->type,
                ],
                'children' => [
                    [
                        'id' => $bookedDevice->id,
                        'event' => 'created',
                        'log_name' => 'BookedDevice',
                        'device_id' => $bookedDevice->device_id,
                        'device_type_id' => $bookedDevice->device_type_id,
                        'device_time_id' => $bookedDevice->device_time_id,
                        'status' => $bookedDevice->status,
                    ]
                ]
            ])
            ->tap(function ($activity) use ($dailyId) {
                $activity->daily_id = $dailyId;
            })
            ->log('SessionDevice transfer');

        //delete any session device if no booked devices left (without triggering events)
        $oldSessionDevice = SessionDevice::withTrashed()->find($bookedDevice->session_device_id);
        if ($oldSessionDevice && $oldSessionDevice->bookedDevices()->count() == 0) {
            $oldSessionDevice->withoutEvents(function () use ($oldSessionDevice) {
                $oldSessionDevice->delete();
            });
        }
        return $updated;
        });
    }
   public function getActivityLogToDevice($id)
   {
    $bookedDevice=BookedDevice::findOrFail($id);

    // Get all related activities
    $orderIds=$bookedDevice->orders->pluck('id')->toArray();
    $sessionDevice = $bookedDevice->sessionDevice()->withTrashed()->first();
    $sessionId = $sessionDevice ? $sessionDevice->id : null;
    $pausesId=$bookedDevice->pauses->pluck('id')->toArray();

    $activities = Activity::where(function ($query) use ($id, $orderIds, $sessionId, $pausesId) {
        $query->where(function ($q) use ($id) {
            $q->where('subject_type', BookedDevice::class)
            ->where('subject_id', $id);
        })
        ->orWhere(function ($q) use ($orderIds) {
            $q->where('subject_type', Order::class)
            ->whereIn('subject_id', $orderIds);
        })
        ->orWhere(function ($q) use ($sessionId) {
            $q->where('subject_type', SessionDevice::class)
            ->where('subject_id', $sessionId);
        })
        ->orWhere(function ($q) use ($pausesId) {
            $q->where('subject_type', BookedDevicePause::class)
            ->whereIn('subject_id', $pausesId);
        });
    })
    ->orderBy('created_at', 'desc')
    ->get();

    return $activities;
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
