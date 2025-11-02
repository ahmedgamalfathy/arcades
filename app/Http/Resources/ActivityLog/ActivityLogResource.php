<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

       return [
            'modelName' => $this->log_name,
            'event' => $this->event,
            'properties' => [
                'id' => $this->properties['id'],
                'name' => $this->properties['name'],
                'price' => $this->properties['price'],
                'bookedDeviceId' => $this->properties['booked_device_id']?BookedDevice::find($this->properties['booked_device_id'])->name:"",
            ],
            'message' => $this->description,
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'userName' => $this->causer_id?User::find($this->causer_id)->name:"",
        ];
    }
}
