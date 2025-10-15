<?php
namespace App\Services\Timer;

use Carbon\Carbon;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Models\Timer\BookedDevice\BookedDevice;

class BookedDeviceService
{
    public function createBookedDevice(array $data)
    {
        return BookedDevice::create($data);
    }

    public function editBookedDevice(int $id)
    {
        return BookedDevice::findOrFail($id);
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
        
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $bookedDevice->start_date_time, 'UTC');
        $end = now('UTC');
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
}
