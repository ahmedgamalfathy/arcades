<?php

namespace App\Http\Resources\Devices\DeviceTime;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTimeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'timeTypeId' => $this->id,
            'name' => $this->name,
            'rate' => $this->rate,
            'deviceTypeId' => $this->device_type_id,
            'deviceId' => $this->device_id,
        ];
    }
}
