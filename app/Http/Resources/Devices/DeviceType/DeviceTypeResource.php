<?php

namespace App\Http\Resources\Devices\DeviceType;

use App\Http\Resources\Devices\Device\Relation\DeviceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'deviceTypeId' => $this->id,
            'name' => $this->name,
            'devices' => DeviceResource::collection($this->whenLoaded('devices')),
        ];
    }
}
