<?php

namespace App\Http\Resources\Devices\Device;

use App\Enums\BookedDevice\BookedDeviceEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Maintenance\MaintenanceResource;
use App\Http\Resources\Devices\DeviceTime\DeviceTimeResource;

class DeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {//name , status , device_type_id , media_id
        $deviceTimes = $this->deviceTimes
            ->merge($this->deviceTimeSpecial)
            ->unique()
            ->values();
        return [
            'deviceId' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'media' => $this->media->path ?? "",
            //totalTime device hours and status bookeddevice
            "time"=>[
                "totalTime"=>  round(($this->bookedDevices()
                    ->where('status',BookedDeviceEnum::FINISHED->value)
                    ->sum('total_used_seconds') - $this->bookedDevices()
                    ->where('status',BookedDeviceEnum::FINISHED->value)
                    ->sum('total_paused_seconds')) / 3600,2) ?? 0,
                "deviceType"=> $this->deviceType->name??"",
                "deviceTypeId"=> $this->device_type_id??"",
                "statusDevice"=> $this->bookedDevices()
                    ->orderBy('id', 'desc')
                    ->first()?->status ?? BookedDeviceEnum::FINISHED->value,

            ],
            // 'deviceType'=>$this->whenLoaded('deviceType',new DeviceResource($this->deviceType)),
            'deviceTimes' =>$this->whenLoaded('deviceTimes',DeviceTimeResource::collection ($deviceTimes)),
            'maintenances' =>$this->whenLoaded('maintenances',MaintenanceResource::collection ($this->maintenances))
        ];
    }
}
