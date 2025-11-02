<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Order\Order;

class OrderItemActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $props = $this->getProperties();

        return [
            'modelName' => $this->log_name,
            'event' => $this->event,
            'properties' => $props,
            'message' => $this->description,
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'userName' => $this->causer?->name ?? '',
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
            'productId' => $attributes['product_id'] ?? '',
            'quantity' => $attributes['quantity'] ?? '',
            'price' => $attributes['price'] ?? '',
            'order' => $attributes['order_id'] ?? '',
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
            'productId' => $attributes['product_id'] ?? '',
            'quantity' => $attributes['quantity'] ?? '',
            'price' => $attributes['price'] ?? '',
        ];
    }
}