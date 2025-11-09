<?php

namespace App\Http\Resources\ActivityLog\Test;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Device\Device;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Timer\SessionDevice\SessionDevice;

class AllDailyActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = $this->resolveResource();
        
        // Add parent info to details if exists (for standalone children)
        if (!empty($this->parentInfo)) {
            $details['parentInfo'] = $this->parentInfo;
        }
        
        // Process all children - filtering is done in controller
        $children = [];
        if (!empty($this->children)) {
            foreach ($this->children as $child) {
                $children[] = $this->resolveChildDetails($child);
            }
        }
        
        return [
            'activityLogId' => $this->id,
            'date' => Carbon::parse($this->created_at)->format('d-M'),
            'time' => Carbon::parse($this->created_at)->format('h:i A'),
            'eventType' => $this->event,
            'userName' => $this->causerName,
            'model' => [
                'modelName' => $this->log_name,
                'modelId' => $this->subject_id,
            ],
            'details' => $details,
            'children' => $children,
        ];
    }

    private function resolveChildDetails($child): array
    {
        $modelName = strtolower($child->log_name);
        $event = $child->event;
        
        $details = match ($modelName) {
            'orderitem' => $this->getOrderItemPropertiesForChild($child, $event),
            'bookeddevice' => $this->getBookedDevicePropertiesForChild($child, $event),
            default => [],
        };
        
        return array_merge([
            'modelName' => $child->log_name,
            'eventType' => $child->event,
        ], $details);
    }

    private function getOrderItemPropertiesForChild($child, string $event): array
    {
        return match($event) {
            'created' => $this->extractOrderItemCreated($child->properties),
            'updated' => $this->extractOrderItemUpdated($child->properties),
            'deleted' => $this->extractOrderItemDeleted($child->properties),
            default => []
        };
    }

    private function getBookedDevicePropertiesForChild($child, string $event): array
    {
        return match($event) {
            'created' => $this->extractBookedDeviceCreated($child->properties),
            'updated' => $this->extractBookedDeviceUpdated($child->properties),
            'deleted' => $this->extractBookedDeviceDeleted($child->properties),
            default => []
        };
    }

    private function extractOrderItemCreated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];
        
        return [
            'productId' => $attributes['product_id'] ?? '',
            'quantity' => $attributes['qty'] ?? '',
            'price' => $attributes['price'] ?? '',
            'total' => ($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0),
        ];
    }

    private function extractOrderItemUpdated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];
        
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

    private function extractOrderItemDeleted($properties): array
    {
        $attributes = $properties['old'] ?? [];
        
        return [
            'productId' => $attributes['product_id'] ?? '',
            'quantity' => $attributes['qty'] ?? '',
            'price' => $attributes['price'] ?? '',
            'total' => ($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0),
        ];
    }

    private function extractBookedDeviceCreated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];
        
        return [
            'bookedDeviceId' => $attributes['id'] ?? '',
            'deviceId' => !empty($attributes['device_id']) 
                ? (Device::find($attributes['device_id'])?->name ?? '') 
                : '',
            'deviceTypeId' => !empty($attributes['device_type_id']) 
                ? (DeviceType::find($attributes['device_type_id'])?->name ?? '') 
                : '',
            'deviceTimeId' => !empty($attributes['device_time_id']) 
                ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '') 
                : '',
            'status' => $attributes['status'] ?? '',
        ];
    }

    private function extractBookedDeviceUpdated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];
        
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

    private function extractBookedDeviceDeleted($properties): array
    {
        $attributes = $properties['old'] ?? [];
        
        return [
            'bookedDeviceId' => $attributes['id'] ?? '',
            'deviceId' => !empty($attributes['device_id']) 
                ? (Device::find($attributes['device_id'])?->name ?? '') 
                : '',
            'deviceTypeId' => !empty($attributes['device_type_id']) 
                ? (DeviceType::find($attributes['device_type_id'])?->name ?? '') 
                : '',
            'deviceTimeId' => !empty($attributes['device_time_id']) 
                ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '') 
                : '',
            'status' => $attributes['status'] ?? '',
        ];
    }

    private function resolveResource(): array
    {
        $modelName = strtolower($this->log_name);
        $event = $this->event;

        return match ($modelName) {
            'order' => $this->getOrderProperties($event),
            'orderitem' => $this->getOrderItemProperties($event),
            'expense' => $this->getExpenseProperties($event),
            'sessiondevice' => $this->getSessionDeviceProperties($event),
            'bookeddevice' => $this->getBookedDeviceProperties($event),
            'bookeddevicepause' => $this->getBookedDevicePauseProperties($event),
            default => [],
        };
    }

    // ==================== Order ====================
    private function getOrderProperties(string $event): array
    {
        return match($event) {
            'created' => $this->getOrderCreatedProperties(),
            'updated' => $this->getOrderUpdatedProperties(),
            'deleted' => $this->getOrderDeletedProperties(),
            default => []
        };
    }

    private function getOrderCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        $bookedDeviceName = '';
        if (!empty($attributes['booked_device_id'])) {
            $device = BookedDevice::find($attributes['booked_device_id']);
            $bookedDeviceName = $device?->name ?? '';
        }
        
        return [
            'name' => $attributes['name'] ?? '',
            'number' => $attributes['number'] ?? '',
            'price' => $attributes['price'] ?? '',
            'bookedDevice' => $bookedDeviceName,
        ];
    }

    private function getOrderUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];
        
        $props = [];
        $importantFields = ['price', 'number', 'name', 'status'];
        
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

    private function getOrderDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'number' => $attributes['number'] ?? '',
            'price' => $attributes['price'] ?? '',
        ];
    }

    // ==================== OrderItem ====================
    private function getOrderItemProperties(string $event): array
    {
        return match($event) {
            'created' => $this->getOrderItemCreatedProperties(),
            'updated' => $this->getOrderItemUpdatedProperties(),
            'deleted' => $this->getOrderItemDeletedProperties(),
            default => []
        };
    }

    private function getOrderItemCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'productId' => $attributes['product_id'] ?? '',
            'quantity' => $attributes['qty'] ?? '',
            'price' => $attributes['price'] ?? '',
            'total'=>($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0),
            'order' => $attributes['order_id'] ?? '',
        ];
    }

    private function getOrderItemUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];
        // Start with static fields from old data        
        // Add changed fields with old/new values
        $fieldsToCheck = ['qty', 'price'];
        foreach ($fieldsToCheck as $field) {
            if (array_key_exists($field, $old) && 
                array_key_exists($field, $attributes) && 
                $old[$field] != $attributes[$field]) {
                $props[$field] = [
                    'old' => $old[$field],
                    'new' => $attributes[$field]
                ];
            }
        }
        
        return $props;
    }

    private function getOrderItemDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'productId' => $attributes['product_id'] ?? '',
            'quantity' => $attributes['qty'] ?? '',
            'price' => $attributes['price'] ?? '',
            'total'=>($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0),
            'order'=>$attributes['order_id']??'',
        ];
    }

    // ==================== Expense ====================
    private function getExpenseProperties(string $event): array
    {
        return match($event) {
            'created' => $this->getExpenseCreatedProperties(),
            'updated' => $this->getExpenseUpdatedProperties(),
            'deleted' => $this->getExpenseDeletedProperties(),
            default => []
        };
    }

    private function getExpenseCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'name' => $attributes['name'] ?? '',
            'price' => $attributes['price'] ?? '',
        ];
    }

    private function getExpenseUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];
        
        $props = [];
        $importantFields = ['name', 'price'];
        
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

    private function getExpenseDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'name' => $attributes['name'] ?? '',
            'price' => $attributes['price'] ?? '',
        ];
    }

    // ==================== SessionDevice ====================
    private function getSessionDeviceProperties(string $event): array
    {
        return match($event) {
            'created' => $this->getSessionDeviceCreatedProperties(),
            'updated' => $this->getSessionDeviceUpdatedProperties(),
            'deleted' => $this->getSessionDeviceDeletedProperties(),
            default => []
        };
    }

    private function getSessionDeviceCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'id' => $attributes['id'] ?? '',
            'name' => $attributes['name'] ?? '',
        ];
    }

    private function getSessionDeviceUpdatedProperties(): array
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

    private function getSessionDeviceDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'id' => $attributes['id'] ?? '',
            'name' => $attributes['name'] ?? '',
        ];
    }

    // ==================== BookedDevice ====================
    private function getBookedDeviceProperties(string $event): array
    {
        return match($event) {
            'created' => $this->getBookedDeviceCreatedProperties(),
            'updated' => $this->getBookedDeviceUpdatedProperties(),
            'deleted' => $this->getBookedDeviceDeletedProperties(),
            default => []
        };
    }

    private function getBookedDeviceCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'bookedDeviceId' => $attributes['id'] ?? '',
            'deviceId' => !empty($attributes['device_id']) 
                ? (Device::find($attributes['device_id'])?->name ?? '') 
                : '',
            'deviceTypeId' => !empty($attributes['device_type_id']) 
                ? (DeviceType::find($attributes['device_type_id'])?->name ?? '') 
                : '',
            'deviceTimeId' => !empty($attributes['device_time_id']) 
                ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '') 
                : '',
            'sessionDeviceId' => !empty($attributes['session_device_id']) 
                ? (SessionDevice::find($attributes['session_device_id'])?->name ?? '') 
                : '',
            'status' => $attributes['status'] ?? '',
        ];
    }

    private function getBookedDeviceUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];
        
        $props = [];
        foreach ($old as $field => $oldValue) {
            if (array_key_exists($field, $attributes) && $oldValue != $attributes[$field]) {
            if ($field !== 'updated_at') {
                $props[$field] = [ 
                    'old' => $oldValue, 
                    'new' => $attributes[$field] 
                ]; 
            }
            }
        }
        
        return $props;
    }

    private function getBookedDeviceDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        
        return [
            'bookedDeviceId' => $attributes['id'] ?? '',
            'deviceId' => !empty($attributes['device_id']) 
                ? (Device::find($attributes['device_id'])?->name ?? '') 
                : '',
            'deviceTypeId' => !empty($attributes['device_type_id']) 
                ? (DeviceType::find($attributes['device_type_id'])?->name ?? '') 
                : '',
            'deviceTimeId' => !empty($attributes['device_time_id']) 
                ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '') 
                : '',
            'status' => $attributes['status'] ?? '',
        ];
    }

    // ==================== BookedDevicePause ====================
    private function getBookedDevicePauseProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        
        return [
            'bookedDeviceId' => $attributes['booked_device_id'] ?? '',
        ];
    }
}