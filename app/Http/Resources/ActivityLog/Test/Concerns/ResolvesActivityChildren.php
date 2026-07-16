<?php

namespace App\Http\Resources\ActivityLog\Test\Concerns;

use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;

trait ResolvesActivityChildren
{
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
        return match ($event) {
            'created' => $this->extractOrderItemCreated($child->properties),
            'updated' => $this->extractOrderItemUpdated($child->properties),
            'deleted' => $this->extractOrderItemDeleted($child->properties),
            default => [],
        };
    }

    private function getBookedDevicePropertiesForChild($child, string $event): array
    {
        return match ($event) {
            'created' => $this->extractBookedDeviceCreated($child->properties),
            'updated' => $this->extractBookedDeviceUpdatedForChild($child->properties, $child->subject_id ?? null),
            'deleted' => $this->extractBookedDeviceDeleted($child->properties),
            'transfer' => $this->extractBookedDeviceCreated($child->properties),
            default => [],
        };
    }

    private function extractBookedDeviceUpdatedForChild($properties, $bookedDeviceId = null): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];
        $props = [];
        $bookedDevice = $bookedDeviceId ? BookedDevice::find($bookedDeviceId) : null;
        $allFields = ['device_id', 'device_type_id', 'device_time_id', 'status', 'end_date_time'];

        foreach ($allFields as $field) {
            $displayField = $this->bookedDeviceDisplayField($field);
            $oldFieldName = $field === 'end_date_time' ? 'old_end_date_time' : $field;
            $hasChanged = (array_key_exists($oldFieldName, $old) || array_key_exists($field, $old))
                && array_key_exists($field, $attributes);

            if ($hasChanged) {
                $oldValue = $old[$oldFieldName] ?? $old[$field] ?? null;
                $newValue = $attributes[$field] ?? null;

                if ($oldValue != $newValue || $field === 'end_date_time') {
                    $props[$displayField] = [
                        'old' => $this->normalizeBookedDeviceFieldValue($field, $oldValue),
                        'new' => $this->normalizeBookedDeviceFieldValue($field, $newValue),
                    ];

                    continue;
                }
            }

            if ($bookedDevice) {
                $props[$displayField] = [
                    'old' => '',
                    'new' => $this->normalizeBookedDeviceFieldValue($field, $bookedDevice->{$field} ?? ''),
                ];
            }
        }

        return $props;
    }

    private function extractOrderItemCreated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];

        return [
            'productId' => $attributes['product_id'] ?? '',
            'productName' => $attributes['product_name'] ?? '',
            'quantity' => [
                'old' => '',
                'new' => $attributes['qty'] ?? '',
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? '',
            ],
            'total' => [
                'old' => '',
                'new' => $attributes['total'] ?? (($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0)),
            ],
        ];
    }

    private function extractOrderItemUpdated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        return [
            'productId' => $attributes['product_id'] ?? '',
            'productName' => $attributes['product_name'] ?? '',
            'quantity' => [
                'old' => $old['qty'] ?? '',
                'new' => $attributes['qty'] ?? '',
            ],
            'price' => [
                'old' => $old['price'] ?? '',
                'new' => $attributes['price'] ?? '',
            ],
            'total' => [
                'old' => isset($old['qty']) && isset($old['price']) ? ($old['qty'] * $old['price']) : ($old['total'] ?? ''),
                'new' => $attributes['total'] ?? (isset($attributes['qty']) && isset($attributes['price']) ? ($attributes['qty'] * $attributes['price']) : ''),
            ],
        ];
    }

    private function extractOrderItemDeleted($properties): array
    {
        $attributes = $properties['old'] ?? [];

        return [
            'productId' => $attributes['product_id'] ?? '',
            'productName' => $attributes['product_name'] ?? '',
            'quantity' => [
                'old' => '',
                'new' => $attributes['qty'] ?? '',
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? '',
            ],
            'total' => [
                'old' => '',
                'new' => $attributes['total'] ?? (($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0)),
            ],
        ];
    }

    private function extractBookedDeviceCreated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];

        return $this->bookedDeviceSnapshot($attributes);
    }

    private function extractBookedDeviceUpdated($properties, $bookedDeviceId = null): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];
        $props = [];
        $bookedDevice = $bookedDeviceId ? BookedDevice::find($bookedDeviceId) : null;
        $allFields = ['device_id', 'device_type_id', 'device_time_id', 'status'];

        foreach ($allFields as $field) {
            $displayField = $this->bookedDeviceDisplayField($field);
            $hasChanged = array_key_exists($field, $old)
                && array_key_exists($field, $attributes)
                && $old[$field] != $attributes[$field];

            if ($hasChanged) {
                $props[$displayField] = [
                    'old' => $this->normalizeBookedDeviceFieldValue($field, $old[$field] ?? ''),
                    'new' => $this->normalizeBookedDeviceFieldValue($field, $attributes[$field] ?? ''),
                ];
            } elseif ($bookedDevice) {
                $value = $this->normalizeBookedDeviceFieldValue($field, $bookedDevice->{$field} ?? '');
                $props[$displayField] = [
                    'old' => $value,
                    'new' => $value,
                ];
            }
        }

        return $props;
    }

    private function extractBookedDeviceDeleted($properties): array
    {
        $attributes = $properties['old'] ?? [];

        return $this->bookedDeviceSnapshot($attributes);
    }

    private function bookedDeviceSnapshot(array $attributes): array
    {
        return [
            'deviceName' => [
                'old' => '',
                'new' => $this->normalizeBookedDeviceFieldValue('device_id', $attributes['device_id'] ?? ''),
            ],
            'deviceType' => [
                'old' => '',
                'new' => $this->normalizeBookedDeviceFieldValue('device_type_id', $attributes['device_type_id'] ?? ''),
            ],
            'deviceTime' => [
                'old' => '',
                'new' => $this->normalizeBookedDeviceFieldValue('device_time_id', $attributes['device_time_id'] ?? ''),
            ],
            'status' => [
                'old' => '',
                'new' => $attributes['status'] ?? '',
            ],
        ];
    }

    private function bookedDeviceDisplayField(string $field): string
    {
        return match ($field) {
            'device_id' => 'deviceName',
            'device_type_id' => 'deviceType',
            'device_time_id' => 'deviceTime',
            'end_date_time' => 'endTime',
            default => $field,
        };
    }

    private function normalizeBookedDeviceFieldValue(string $field, $value): mixed
    {
        if (empty($value)) {
            return '';
        }

        return match ($field) {
            'device_id' => Device::find($value)?->name ?? '',
            'device_type_id' => DeviceType::find($value)?->name ?? '',
            'device_time_id' => DeviceTime::find($value)?->name ?? '',
            default => $value ?? '',
        };
    }
}
