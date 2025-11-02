<?php

namespace App\Http\Resources\ActivityLog\DeviceActivity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllDeviceActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
         $propsArray = is_array($this->properties)
            ? $this->properties
            : json_decode(json_encode($this->properties), true);

        $event = $this->event ?? $this->description ?? 'unknown';

        return [
            'modelName' => class_basename($this->subject_type),
            'event' => $event,
            'message' => $this->description ?? '',
            'properties' => [
                'old' => $propsArray['old'] ?? null,
                'attributes' => $propsArray['attributes'] ?? null,
                'daily_id' => $propsArray['daily_id'] ?? null,
            ],
            'userName' => optional($this->causer)->name ?? 'System',
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
