<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;
use App\Models\Device\Device;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Timer\SessionDevice\SessionDevice;

class BookedDeviceActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $props = $this->getProperties();
       return [
            'modelName' => $this->log_name,
            'event' => $this->event,
            'properties' => $props,
            'message' => $this->description,
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'userName' => $this->causer_id?User::find($this->causer_id)->name:"",
        ];
    }
    private function getProperties(): array
    {
        return match($this->event) {
            'created' => $this->getCreatedProperties(),
            'updated' => $this->getUpdatedProperties(),
            'deleted' => $this->getDeletedProperties(),
            default => []
        };
    }
    public function getCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'bookedDeviceId' => $attributes['id'] ?? '',
            'deviceId' => $attributes['device_id'] ? Device::find($attributes['device_id'])->name : '',
            'deviceTypeId' => $attributes['device_type_id'] ? DeviceType::find($attributes['device_type_id'])->name : '',
            'deviceTimeId' => $attributes['device_time_id'] ? DeviceTime::find($attributes['device_time_id'])->name : '',
            'sessionDeviceId' => $attributes['session_device_id'] ? SessionDevice::find($attributes['session_device_id'])->name : '',
            'status' => $attributes['status'] ?? '',
        ];
    }
    public function getUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];
        
        $props = [];
        
        foreach ($old as $field => $oldValue) {
            if (array_key_exists($field, $attributes) && $oldValue != $attributes[$field]) {
                $props[$field] = [
                    'old' => $oldValue,
                    'new' => $attributes[$field]
                ];
            }
        }
        
        return $props;
    }
    public function getDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'bookedDeviceId' => $attributes['id'] ?? '',
            'deviceId' => $attributes['device_id'] ? Device::find($attributes['device_id'])->name : '',
            'deviceTypeId' => $attributes['device_type_id'] ? DeviceType::find($attributes['device_type_id'])->name : '',
            'deviceTimeId' => $attributes['device_time_id'] ? DeviceTime::find($attributes['device_time_id'])->name : '',
            'status' => $attributes['status'] ?? '',
        ];
    }
}
