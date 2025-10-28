<?php
namespace App\Services\Timer;

use App\Enums\BookedDevice\BookedDeviceEnum;
use Carbon\Carbon;
use App\Models\Timer\BookedDevice\BookedDevice;

class DeviceTimerService
{
    public function __construct(
        protected BookedDeviceService $bookedDeviceService,
        protected BookedDevicePauseService $pauseService
    ) {}

    // ✅ start أو إدخال وقت محدد
    public function startOrSetTime(array $data)
    {//sessionDeviceId,deviceTypeId,deviceId,deviceTimeId,startDateTime,endDateTime
        if (!empty($data['startDateTime']) && !empty($data['endDateTime'])) {
            $data['status'] = BookedDeviceEnum::ACTIVE->value;
            $data['totalUsedSeconds'] = Carbon::parse($data['startDateTime'])
                ->diffInSeconds(Carbon::parse($data['endDateTime']));
        } else {
           $data['status'] = BookedDeviceEnum::ACTIVE->value;
        }

        return $this->bookedDeviceService->createbookedDevice($data);
    }


    public function pause(int $id)
    {
         $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status== BookedDeviceEnum::FINISHED->value){
            throw new \Exception('Device is already finished.');
        }
        if ($bookedDevice->status !== BookedDeviceEnum::ACTIVE->value) {
            throw new \Exception('Device is already paused.');
        }
        $this->pauseService->createPause($id);
        $this->bookedDeviceService->updateBookedDevice($id, ['status' =>  BookedDeviceEnum::PAUSED->value]);
    }

    public function resume(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if ( $bookedDevice->status !== BookedDeviceEnum::PAUSED->value) {
            throw new \Exception('Device is not paused.');
        }
        $this->pauseService->resumePause($id);
        //عايز اقوله لو الجهاز ليه نهاية حدث المبلغ برده
        $this->bookedDeviceService->updateBookedDevice($id, ['status' =>  BookedDeviceEnum::ACTIVE->value]);
    }

    public function finish(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status== BookedDeviceEnum::FINISHED->value){
             return $bookedDevice;
            // throw new \Exception('Device is already finished.');
        }
        return $this->bookedDeviceService->finishBookedDevice($id);
    }

    public function changeDeviceTime($id, int $newTimeId)
    {
      $bookedDevice= $this->bookedDeviceService->finishBookedDevice($id);

        return $this->bookedDeviceService->createBookedDevice([
            'session_device_id' => $bookedDevice->session_device_id,
            'device_id' => $bookedDevice->device_id,
            'device_type_id' => $bookedDevice->device_type_id,
            'device_time_id' => $newTimeId,
            'start_date_time' => now(),
            'status' => BookedDeviceEnum::ACTIVE->value,
        ]);
    }
}
