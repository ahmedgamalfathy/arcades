<?php

namespace App\Http\Resources\Timer\BookedDevice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Order\AllOrderResource;
class BookedDevcieResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [//sessionDevice,deviceType,deviceTime,device
            'bookedDeviceId' => $this->id,
            'sessionDeviceId'=>$this->session_device_id??"",
            'deviceTypeId' => $this->device_type_id,
            'deviceTimeId' => $this->device_time_id,
            'deviceId' => $this->device_id,
            'device'=>[
                'deviceTypeName' => $this->deviceType->name,
                'deviceTimeName' => $this->deviceTime->name,
                'deviceName' => $this->device->name,
                'path'=>$this->device->media->path??"",
            ],
            'startDateTime' => $this->start_date_time ? Carbon::parse($this->start_date_time)->format('H:i') : "",
            'endDateTime' => $this->end_date_time ? Carbon::parse($this->end_date_time)->format('H:i') : "",
            'status' => $this->status,
            'totalUsedSeconds' => $this->total_used_seconds,
            'totalPausedSeconds'=>$this->total_paused_seconds,
            'price'=>$this->period_cost,
            'orders'=>$this->orders?AllOrderResource::collection($this->orders):"",
        ];
    }
}
