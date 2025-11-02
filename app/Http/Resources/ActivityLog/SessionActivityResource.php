<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

class SessionActivityResource extends JsonResource
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
            'properties' => [
                'id'=>$props['id']??"",
                'name'=>$props['name']??"",
            ],
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
    private function getCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'id' => $attributes['id'] ?? '',
            'name' => $attributes['name'] ?? '',
        ];
    }
    private function getUpdatedProperties(): array
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
    private function getDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'id' => $attributes['id'] ?? '',
            'name' => $attributes['name'] ?? '',
        ];
    }
}
