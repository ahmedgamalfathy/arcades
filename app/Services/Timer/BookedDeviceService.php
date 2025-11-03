<?php
namespace App\Services\Timer;

use Exception;
use Carbon\Carbon;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Timer\BookedDevicePause\BookedDevicePause;
use Spatie\Activitylog\Models\Activity;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
class BookedDeviceService
{
    public function createBookedDevice(array $data)
    {
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

    public function finishBookedDevice(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status == BookedDeviceEnum::FINISHED->value)
        {
            throw new Exception("the booked device Finished status");
        }
        $start = Carbon::parse($bookedDevice->start_date_time)->timezone('UTC');
        $end = Carbon::now('UTC');
        if($bookedDevice->end_date_time && $bookedDevice->end_date_time->timezone('UTC')->lessThan($end)) {
            $end = $bookedDevice->end_date_time->timezone('UTC');
        }
        $total = $start->diffInSeconds($end);
        $used = max(0, $total - (int) $bookedDevice->total_paused_seconds);
        $bookedDevice->update([
            'end_date_time' => $end,
            'total_used_seconds' => $used,
            'status' => BookedDeviceEnum::FINISHED->value
        ]);
        $bookedDevice->period_cost=$bookedDevice->calculatePrice();
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
        }else {
            $bookedDevice->delete();
            $bookedDevice->sessionDevice->delete();
        }
    }
    public function updateEndDateTime(int $id, array $data)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status == BookedDeviceEnum::FINISHED->value)
        {
            throw new Exception("the booked device Finished status");
        }
        $bookedDevice->end_date_time =$data['endDateTime'];
        $bookedDevice->total_used_seconds=$bookedDevice->calculateUsedSeconds();
        $bookedDevice->period_cost=$bookedDevice->calculatePrice();
        $bookedDevice->save();
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
            ]);
            $data['sessionDeviceId'] = $sessionDevice->id;
        }

        if ($bookedDevice->status === BookedDeviceEnum::FINISHED->value) {
            throw new Exception("The booked device has a finished status.");
        }

        if ($bookedDevice->session_device_id === $data['sessionDeviceId']) {
            throw new Exception("The booked device is already in this session device.");
        }

        $bookedDevice->update(['session_device_id' => $data['sessionDeviceId']]);
        return $bookedDevice;
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
