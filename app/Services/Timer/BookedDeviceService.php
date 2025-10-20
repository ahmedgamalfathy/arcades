<?php
namespace App\Services\Timer;

use Exception;
use Carbon\Carbon;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Enums\SessionDevice\SessionDeviceEnum;

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
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $bookedDevice->start_date_time, 'UTC');
        $end = now('UTC');
        if($bookedDevice->end_date_time && $bookedDevice->end_date_time->lessThan($end)){
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
        $bookedDevice->save();
        return $bookedDevice->fresh();
    }
    public function deleteBookedDevice(int $id)
    {
        $bookedDevice= BookedDevice::findOrFail($id);
        if($bookedDevice && $bookedDevice->sessionDevice->type==SessionDeviceEnum::GROUP->value) 
        {
            if($bookedDevice->sessionDevice->devices()->count() == 1){
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

}
