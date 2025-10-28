<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
class BookedDeviceStatusNotification extends Notification  
{
    use Queueable;
    protected $data;
    /**
     * Create a new notification instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }
    public function databaseConnection()
    {
        return 'tenant'; 
    }

   

    public function toArray(object $notifiable): array
    {
        return [
              'message' => $this->data['message'],
              'deviceTypeName' => $this->data['deviceTypeName'],
              'deviceTimeName' => $this->data['deviceTimeName'],
              'deviceName' => $this->data['deviceName'],
              'bookedDeviceId' => $this->data['bookedDeviceId'],
              'userId' => $this->data['userId'],        
              'created_at' => $this->data['created_at'],
              'updated_at' => $this->data['updated_at'],
        ];
    }

}
