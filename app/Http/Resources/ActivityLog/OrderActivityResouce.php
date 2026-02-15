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
        // تحويل properties من JSON إلى array إذا لزم الأمر
        $properties = is_string($this->properties)
            ? json_decode($this->properties, true)
            : $this->properties;

        $props = $this->getProperties($properties);

        return [
            'activityLogId' => $this->id,
            'date' => $this->created_at?->format('d-M'),
            'time' => $this->created_at?->format('h:i A'),
            'eventType' => $this->event,
            'userName' => $this->causer?->name ?? '',
            'model' => [
                'modelName' => $this->log_name,
                'modelId' => $this->subject_id,
            ],
            'details' => $props,
            'children' => $properties['children'] ?? [],
        ];
    }

    private function getProperties($properties): array
    {
        return match($this->event) {
            'created' => $this->getCreatedProperties($properties),
            'updated' => $this->getUpdatedProperties($properties),
            'deleted' => $this->getDeletedProperties($properties),
            default => []
        };
    }

    private function getCreatedProperties($properties): array
    {
        $attributes = $properties['attributes'] ?? [];

        return [
            'name' => $attributes['name'] ?? '',
            'number' => $attributes['number'] ?? '',
            'price' => $attributes['price'] ?? '',
            'bookedDevice' => $attributes['booked_device_id'] ? BookedDevice::find($attributes['booked_device_id'])->name : '',
        ];
    }

    private function getUpdatedProperties($properties): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        $props = [];
        $importantFields = ['price', 'number', 'quantity', 'status'];

        foreach ($importantFields as $field) {
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

    private function getDeletedProperties($properties): array
    {
        $attributes = $properties['old'] ?? [];

        return [
            'number' => $attributes['number'] ?? '',
            'price' => $attributes['price'] ?? '',
        ];
    }
}
