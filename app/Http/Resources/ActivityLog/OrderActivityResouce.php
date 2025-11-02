<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;
use App\Models\Order\OrderItem;

class OrderActivityResouce extends JsonResource
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
            'userName' => $this->causer?->name ?? '', // ✅ استخدم relation بدل find
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

    private function getCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'name' => $attributes['name'] ?? '',
            'number' => $attributes['number'] ?? '',
            'price' => $attributes['price'] ?? '',
            'bookedDevice' => $attributes['booked_device_id'] ?BookedDevice::find($attributes['booked_device_id'])->name: '',
        ];
    }

    private function getUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];
        
        $props = [];
        $importantFields = ['price', 'number', 'quantity', 'status'];
        
        foreach ($importantFields as $field) {
            // ✅ استخدم array_key_exists بدل isset
            if (array_key_exists($field, $attributes) && 
                array_key_exists($field, $old) && 
                $old[$field] != $attributes[$field]) {
                
                $props[$field] = [
                    'old' => $old[$field],
                    'new' => $attributes[$field]
                ];
            }
        }
        
        return $props;
    }

    private function getDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'number' => $attributes['number'] ?? '',
            'price' => $attributes['price'] ?? '',
        ];
    }
}
