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

class BookedDeviceService
{
    public function allBookedDevices(Request $request)
    {
        $perPage = $request->query('perPage', 10);
        $bookedDevices= QueryBuilder::for(BookedDevice::class)
        ->with('sessionDevice','deviceType','deviceTime','device')
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
        return $bookedDevice->fresh();
    }
    public function deleteBookedDevice(int $id)
    {
        $bookedDevice= BookedDevice::findOrFail($id);
        if($bookedDevice && $bookedDevice->sessionDevice->type==SessionDeviceEnum::GROUP->value)
        {
            if($bookedDevice->sessionDevice->bookedDevices()->count() == 1){
                $bookedDevice->sessionDevice->delete();
            }
            if($bookedDevice->pauses()->count() > 0){
                $bookedDevice->pauses()->delete();
            }
            $bookedDevice->delete();
            // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();
        }else {
            $bookedDevice->delete();
            $bookedDevice->sessionDevice->delete();
            // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();
        }
    }
    public function updateEndDateTime(int $id, array $data)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status == BookedDeviceEnum::FINISHED->value)
        {
            throw new Exception("the booked device Finished status");
        }
        $bookedDevice->end_date_time = Carbon::parse($data['endDateTime'])->utc();
        $bookedDevice->total_used_seconds=$bookedDevice->calculateUsedSeconds();
        $bookedDevice->period_cost=$bookedDevice->calculatePrice();
        $bookedDevice->save();
        // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();
        return $bookedDevice;
    }
    public function transferDeviceToGroup(int $id, array $data)
    {
        $bookedDevice = BookedDevice::findOrFail($id);

        if ($data['sessionDeviceId'] ?? null) {
            $sessionDevice = SessionDevice::findOrFail($data['sessionDeviceId']);

            if ($sessionDevice->type === SessionDeviceEnum::INDIVIDUAL->value) {
                throw new Exception("The session device type must be group.");
            }
        } elseif ($data['name'] ?? null) {
            $sessionDevice = SessionDevice::create([
                'name' => $data['name'],
                'type' => SessionDeviceEnum::GROUP->value,
                'daily_id' => $bookedDevice->sessionDevice->daily_id,
            ]);
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
        // broadcast(new BookedDeviceChangeStatus($bookedDevice))->toOthers();
        return $updated;
    }
    public function transferBookedDeviceToSessionDevice(int $bookedDeviceId ,$dailyId)
    {
        return DB::transaction(function () use ($bookedDeviceId, $dailyId) {
        $bookedDevice = BookedDevice::findOrFail($bookedDeviceId);
        if ($bookedDevice->sessionDevice->type === SessionDeviceEnum::INDIVIDUAL->value) {
            throw new Exception("The session device type must be group.");
        }
        if ($bookedDevice->status === BookedDeviceEnum::FINISHED->value) {
            throw new Exception("The booked device has a finished status.");
        }
        $newSessionDevice = SessionDevice::create([
            'name' =>'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $dailyId,
        ]);
        return BookedDevice::where('session_device_id', $bookedDevice->session_device_id)
        ->where('device_id', $bookedDevice->device_id)
        ->where('device_type_id', $bookedDevice->device_type_id)
        ->update([
        'session_device_id' => $newSessionDevice->id,
        ]);
        });
    }
   public function getActivityLogToDevice($id)
   {
    $bookedDevice=BookedDevice::findOrFail($id);
    $orderIds=$bookedDevice->orders->pluck('id')->toArray();
    $orderItemIds=$bookedDevice->orders->pluck('order_items.id')->toArray();
    $sessionId = [$bookedDevice->sessionDevice->id];
    $pausesId=$bookedDevice->pauses->pluck('id')->toArray();
        $activities  = Activity::where(function ($query) use ($orderIds, $orderItemIds, $sessionId, $pausesId) {
            $query->where(function ($q) use ($orderIds) {
                $q->where('subject_type', Order::class)
                ->whereIn('subject_id', $orderIds);
            })
            ->orWhere(function ($q) use ($orderItemIds) {
                $q->where('subject_type', OrderItem::class)
                ->whereIn('subject_id', $orderItemIds);
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
    return [
        'orders'      => $activities->where('subject_type', Order::class)->values(),
        'order_items' => $activities->where('subject_type', OrderItem::class)->values(),
        'sessions'    => $activities->where('subject_type', SessionDevice::class)->values(),
        'pauses'      => $activities->where('subject_type', BookedDevicePause::class)->values(),
    ];

   }
}
