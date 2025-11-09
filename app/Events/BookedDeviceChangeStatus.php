<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
class BookedDeviceChangeStatus implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $bookedDevice;
    public function __construct($bookedDevice)
    {
        $this->bookedDevice =  $bookedDevice->load(['device', 'deviceType', 'deviceTime','sessionDevice']);
    }

    /**
     * تحديد القناة اللي هيتبعت عليها البث
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('booked-devices'),
        ];
    }
    /**
     * اسم الـ Event
     */
    public function broadcastAs(): string
    {
        return 'booked-device-change-status';
    }
    public function broadcastWith(): array
    {
        return [
            'id' => $this->bookedDevice->id,
            'deviceId' => $this->bookedDevice->device_id,
            'deviceTypeId' => $this->bookedDevice->device_type_id,
            'deviceTimeId' => $this->bookedDevice->device_time_id,
            'sessionDeviceId' => $this->bookedDevice->session_device_id,
            'status' => $this->bookedDevice->status,
            'startDateTime' => $this->bookedDevice->start_date_time,
            'endDateTime' => $this->bookedDevice->end_date_time,
            'currentTime' => gmdate('H:i:s', Carbon::now()->diffInSeconds(Carbon::parse($this->bookedDevice->start_date_time))),
            'totalPausedSeconds' => $this->bookedDevice->total_paused_seconds,
            'totalUsedSeconds' => $this->bookedDevice->total_used_seconds,
            'periodCost' => $this->bookedDevice->period_cost,
        ];
    }
}
