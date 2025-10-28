<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookedDeviceExpireTime implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $notification;
    public function __construct($notification)
    {
        $this->notification = $notification;
    }

    /**
     * تحديد القناة اللي هيتبعت عليها البث
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('booked-device-expire-time'),
        ];
    }
    /**
     * اسم الـ Event
     */
        public function broadcastAs(): string
    {
        return 'device-expire-time';
    }
    public function broadcastWith(): array
    {
        return [
            'id' => uniqid(),
            'message' => $this->notification['message'],
            'deviceTypeName' => $this->notification['deviceTypeName'],
            'deviceTimeName' => $this->notification['deviceTimeName'],
            'deviceName' => $this->notification['deviceName'],
            'bookedDeviceId' => $this->notification['bookedDeviceId'],
            'sessionDevice' => $this->notification['sessionDevice'],
            'timestamp' => now()->toISOString(),
        ];
    }
    
}
