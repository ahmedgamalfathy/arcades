<?php
namespace App\Services\Timer;

use App\Enums\BookedDevice\BookedDeviceEnum;
use Carbon\Carbon;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Events\BookedDeviceChangeStatus;

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
        //  broadcast(new BookedDeviceChangeStatus($bookedDevice->fresh()))->toOthers();
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
        // broadcast(new BookedDeviceChangeStatus($bookedDevice->fresh()))->toOthers();
    }

    public function finish(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status== BookedDeviceEnum::PAUSED->value){
            $this->resume($id);
        }
        if($bookedDevice->status== BookedDeviceEnum::FINISHED->value){
             return $bookedDevice;
            // throw new \Exception('Device is already finished.');
        }
        $finished = $this->bookedDeviceService->finishBookedDevice($id);
        // broadcast(new BookedDeviceChangeStatus($finished))->toOthers();
        return $finished;
    }

    public function changeDeviceTime($id, int $newTimeId)
    {
      $oldBookedDevice = BookedDevice::findOrFail($id);
         $newEndDateTime = null;
        if ($oldBookedDevice->end_date_time) {
            $endDateTime = Carbon::parse($oldBookedDevice->end_date_time);
            $now = Carbon::now();

            if ($endDateTime->lessThan($now)) {
                throw new \Exception('لا يمكن تغيير نوع الوقت لأن وقت الجهاز انتهى');
            }

            $newEndDateTime = $endDateTime;
        }
      $bookedDevice= $this->bookedDeviceService->finishBookedDevice($id);
      $BookedDeviceChange=$this->bookedDeviceService->createBookedDevice([
            'sessionDeviceId' => $bookedDevice->session_device_id,
            'deviceId' => $bookedDevice->device_id,
            'deviceTypeId' => $bookedDevice->device_type_id,
            'deviceTimeId' => $newTimeId,
            'startDateTime' => now(),
            'endDateTime' => $newEndDateTime,
            'status' => $oldBookedDevice->status == BookedDeviceEnum::PAUSED->value ? BookedDeviceEnum::PAUSED->value : BookedDeviceEnum::ACTIVE->value,
        ]);
        // broadcast(new BookedDeviceChangeStatus($bookedDevice->fresh()))->toOthers();
        return $BookedDeviceChange;
    }
}
