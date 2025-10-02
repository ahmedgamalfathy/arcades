<?php

namespace App\Http\Resources\Devices\DeviceType;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Devices\DeviceTime\DeviceTimeResource;


class DeviceTypeEditResource extends JsonResource
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
            'deviceTimes' => DeviceTimeResource::collection($this->deviceTimes),
        ];
    }
}
