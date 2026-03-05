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

        // Create pause without triggering events/logging
        $pause = null;
        $bookedDevice->pauses()->make([
            'paused_at' => Carbon::now('UTC'),
        ])->saveQuietly();

        return $bookedDevice;
    }

    public function resumePause(int $id)
    {
        $bookedDevice=BookedDevice::findOrFail($id);
        $pause = $bookedDevice->pauses()->whereNull('resumed_at')->latest()->first();

        if ($pause) {
            // Update pause without triggering events/logging
            $pause->withoutEvents(function() use ($pause, $bookedDevice) {
                $pause->update([
                    'resumed_at' => Carbon::now(),
                    'duration_seconds' => $pause->paused_at->diffInSeconds(Carbon::now()),
                ]);

                $bookedDevice->increment('total_paused_seconds', $pause->duration_seconds);
                if($bookedDevice->end_date_time){
                    $bookedDevice->end_date_time = Carbon::parse($bookedDevice->end_date_time)
                        ->addSeconds($pause->duration_seconds);
                    $bookedDevice->period_cost = $bookedDevice->calculatePrice();
                    $bookedDevice->save();
                }
            });
        }

        return $pause;
    }
}
