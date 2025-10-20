<?php
namespace App\Services\Timer;

use Carbon\Carbon;
use App\Services\Timer\BookedDeviceService;
use App\Models\Timer\BookedDevice\BookedDevice;


class BookedDevicePauseService
{
    public function createPause(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        $bookedDevice->pauses()->create([
            'paused_at' => now(),
        ]);
        return $bookedDevice;

    }

    public function resumePause(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        $pause = $bookedDevice->pauses()->whereNull('resumed_at')->latest()->first();

        if ($pause) {
            $pause->update([
                'resumed_at' => now(),
                'duration_seconds' => $pause->paused_at->diffInSeconds(now()),
            ]);

            $bookedDevice->increment('total_paused_seconds', $pause->duration_seconds);
            if($bookedDevice->end_date_time){
                $bookedDevice->end_date_time = Carbon::parse($bookedDevice->end_date_time)
                    ->addSeconds($pause->duration_seconds);
                $bookedDevice->period_cost = $bookedDevice->calculatePrice();
                $bookedDevice->save();
            }
        }

        return $pause;
    }
}
