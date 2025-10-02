<?php

namespace App\Http\Resources\Devices\Device\Relation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        return [
            'deviceId' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'media' => $this->media->path ?? "",
            // 'deviceType'=>$this->whenLoaded('deviceType',new DeviceResource($this->deviceType)),
            'deviceTimes' =>$this->deviceTimes->pluck('name')??""
            // $this->whenLoaded('deviceTimes',DeviceTimeResource::collection ($this->deviceTimes))
        ];
    }
}
