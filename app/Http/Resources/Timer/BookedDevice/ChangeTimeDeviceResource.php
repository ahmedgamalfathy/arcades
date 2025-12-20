<?php

namespace App\Http\Resources\Timer\BookedDevice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Order\AllOrderResource;
use App\Models\Timer\BookedDevice\BookedDevice;
class ChangeTimeDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [//sessionDevice,deviceType,deviceTime,device
                // 'bookedDeviceId'=>$this->id,
                'deviceTypeName' => $this->deviceType->name,
                'deviceTimeName' => $this->deviceTime->name,
                'deviceName' => $this->device->name,
                'periodPrice'=>$this->deviceTime->rate,
                'startDateTime' => $this->start_date_time ? Carbon::parse($this->start_date_time)->format('H:i') : "",
                'endDateTime' => $this->end_date_time ? Carbon::parse($this->end_date_time)->format('H:i') : "",
                'realTimeUsed' => round($this->calculateUsedSeconds()/60,2),
                'priceUsed'=>$this->current_device_cost,
        ];
    }
}
