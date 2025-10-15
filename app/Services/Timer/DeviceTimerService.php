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
    {
        if (!empty($data['start_date_time']) && !empty($data['end_date_time'])) {
            $data['status'] = BookedDeviceEnum::ACTIVE->value;
            $data['total_used_seconds'] = Carbon::parse($data['start_date_time'])
                ->diffInSeconds(Carbon::parse($data['end_date_time']));
        } else {
           $data['status'] = BookedDeviceEnum::ACTIVE->value;
        }

        return $this->bookedDeviceService->createbookedDevice($data);
    }


    public function pause(int $id)
    {
         $bookedDevice=BookedDevice::findOrFail($id);
        if ($bookedDevice->status !== BookedDeviceEnum::ACTIVE->value) {
            throw new \Exception('Device is not active.');
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
