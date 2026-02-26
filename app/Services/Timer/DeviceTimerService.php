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

        // Disable automatic logging
        $oldStatus = $bookedDevice->status;

        // Create pause without logging
        $this->pauseService->createPause($id);

        // Update status without automatic logging
        $bookedDevice->withoutEvents(function() use ($bookedDevice) {
            $bookedDevice->update(['status' => BookedDeviceEnum::PAUSED->value]);
        });

        // Manual activity log for SessionDevice with BookedDevice as child
        $this->logSessionDeviceAction($bookedDevice, $oldStatus);

        //  broadcast(new BookedDeviceChangeStatus($bookedDevice->fresh()))->toOthers();
    }

    public function resume(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if ( $bookedDevice->status !== BookedDeviceEnum::PAUSED->value) {
            throw new \Exception('Device is not paused.');
        }

        $oldStatus = $bookedDevice->status;

        // Resume pause without logging
        $this->pauseService->resumePause($id);

        // Update status without automatic logging
        $bookedDevice->withoutEvents(function() use ($bookedDevice) {
            $bookedDevice->update(['status' => BookedDeviceEnum::ACTIVE->value]);
        });

        // Manual activity log for SessionDevice with BookedDevice as child
        $this->logSessionDeviceAction($bookedDevice, $oldStatus);

        // broadcast(new BookedDeviceChangeStatus($bookedDevice->fresh()))->toOthers();
    }

    public function finish(int $id ,array $data = [])
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        if($bookedDevice->status== BookedDeviceEnum::PAUSED->value){
            $this->resume($id);
            $bookedDevice->refresh();
        }
        if($bookedDevice->status== BookedDeviceEnum::FINISHED->value){
             return $bookedDevice;
        }

        $oldStatus = $bookedDevice->status;

        // Finish without automatic logging
        $finished = $this->finishBookedDeviceWithoutLog($bookedDevice, $data);

        // Manual activity log for SessionDevice with BookedDevice as child
        $this->logSessionDeviceAction($finished, $oldStatus);

        // broadcast(new BookedDeviceChangeStatus($finished))->toOthers();
        return $finished;
    }

    private function finishBookedDeviceWithoutLog(BookedDevice $bookedDevice, array $data = [])
    {
        $start = $bookedDevice->start_date_time;
        $end = Carbon::now();
        if($bookedDevice->end_date_time && $bookedDevice->end_date_time->lessThan($end)) {
            $end = $bookedDevice->end_date_time;
        }
        $total = $start->diffInSeconds($end);
        $used = max(0, $total - (int) $bookedDevice->total_paused_seconds);

        // Update without triggering events
        $bookedDevice->withoutEvents(function() use ($bookedDevice, $end, $used, $data) {
            $bookedDevice->update([
                'end_date_time' => $end,
                'total_used_seconds' => $used,
                'status' => BookedDeviceEnum::FINISHED->value
            ]);
            $bookedDevice->period_cost = $bookedDevice->calculatePrice();
            $bookedDevice->actual_paid_amount = $data['actualPaidAmount'] ?? $bookedDevice->total_cost;
            $bookedDevice->save();

            $bookedDevice->orders->each(function ($order) {
                $order->update(['is_paid' => 1]);
            });
        });

        return $bookedDevice->fresh();
    }

    private function logSessionDeviceAction(BookedDevice $bookedDevice, int $oldStatus)
    {
        $sessionDevice = $bookedDevice->sessionDevice;

        if (!$sessionDevice) {
            return;
        }

        // Create activity log for SessionDevice with BookedDevice as child
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
                    'old_status' => $oldStatus,
                ]],
            ])
            ->tap(function ($activity) use ($sessionDevice) {
                $activity->daily_id = $sessionDevice->daily_id;
            })
            ->log('SessionDevice action on BookedDevice');
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
