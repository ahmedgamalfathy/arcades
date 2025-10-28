<?php

namespace App\Http\Resources\Notification;

use App\Models\Timer\BookedDevice\BookedDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //id,read_at,deviceTypeName,deviceTimeName,deviceName,bookedDeviceId,userId,createdAt,updatedAt
        $data_decode =json_decode($this->data,true);
        return [
            'notificationId' => $this->id,
            'data' => [
                'deviceTypeName' => $data_decode['deviceTypeName'],
                'deviceTimeName' => $data_decode['deviceTimeName'],
                'deviceName' => $data_decode['deviceName'],
                'bookedDeviceId' => $data_decode['bookedDeviceId'],
                'userId' => $data_decode['userId'],
                'message' => $data_decode['message']??"",
                'createdAt' =>Carbon::parse($this->created_at)->diffForHumans(),
                'readAt' => $this->read_at??"",
                'path'=>BookedDevice::find($data_decode['bookedDeviceId'])->device->media->path??"",
            ],
        ];
    }
}
