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
            'updated' => $this->extractBookedDeviceUpdatedForChild($child->properties, $child->subject_id ?? null),
            'deleted' => $this->extractBookedDeviceDeleted($child->properties),
            default => []
        };
    }

    private function extractBookedDeviceUpdatedForChild($properties, $bookedDeviceId = null): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        $props = [];

        // Get the current BookedDevice to access all fields
        $bookedDevice = null;
        if ($bookedDeviceId) {
            $bookedDevice = BookedDevice::find($bookedDeviceId);
        }

        $allFields = ['device_id', 'device_type_id', 'device_time_id', 'status', 'end_date_time'];

        foreach ($allFields as $field) {
            $displayField = match($field) {
                'device_id' => 'deviceName',
                'device_type_id' => 'deviceType',
                'device_time_id' => 'deviceTime',
                'status' => 'status',
                'end_date_time' => 'endTime',
                default => $field
            };

            // Check if field changed (special handling for end_date_time with old_end_date_time)
            $oldFieldName = $field === 'end_date_time' ? 'old_end_date_time' : $field;

            $hasChanged = (array_key_exists($oldFieldName, $old) || array_key_exists($field, $old)) &&
                         array_key_exists($field, $attributes);

            if ($hasChanged) {
                $oldValue = $old[$oldFieldName] ?? $old[$field] ?? '';
                $newValue = $attributes[$field] ?? '';

                // Check if values are actually different
                // For end_date_time, always show if it exists in properties (even if both are empty)
                if ($oldValue != $newValue || $field === 'end_date_time') {
                    // Convert IDs to names
                    if ($field === 'device_id') {
                        $oldValue = !empty($oldValue) ? (Device::find($oldValue)?->name ?? '') : '';
                        $newValue = !empty($newValue) ? (Device::find($newValue)?->name ?? '') : '';
                    } elseif ($field === 'device_type_id') {
                        $oldValue = !empty($oldValue) ? (DeviceType::find($oldValue)?->name ?? '') : '';
                        $newValue = !empty($newValue) ? (DeviceType::find($newValue)?->name ?? '') : '';
                    } elseif ($field === 'device_time_id') {
                        $oldValue = !empty($oldValue) ? (DeviceTime::find($oldValue)?->name ?? '') : '';
                        $newValue = !empty($newValue) ? (DeviceTime::find($newValue)?->name ?? '') : '';
                    }

                    $props[$displayField] = [
                        'old' => $oldValue,
                        'new' => $newValue
                    ];
                    continue;
                }
            }

            // Field didn't change or not in properties - show with old=""
            if ($bookedDevice) {
                $value = $bookedDevice->{$field} ?? '';

                // Convert IDs to names
                if ($field === 'device_id') {
                    $value = !empty($value) ? (Device::find($value)?->name ?? '') : '';
                } elseif ($field === 'device_type_id') {
                    $value = !empty($value) ? (DeviceType::find($value)?->name ?? '') : '';
                } elseif ($field === 'device_time_id') {
                    $value = !empty($value) ? (DeviceTime::find($value)?->name ?? '') : '';
                }

                $props[$displayField] = [
                    'old' => '',
                    'new' => $value
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
                'new' => $attributes['qty'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
            'total' => [
                'old' => '',
                'new' => $attributes['total'] ?? (($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0))
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
                'new' => $attributes['qty'] ?? ''
            ],
            'price' => [
                'old' => $old['price'] ?? '',
                'new' => $attributes['price'] ?? ''
            ],
            'total' => [
                'old' => isset($old['qty']) && isset($old['price']) ? ($old['qty'] * $old['price']) : ($old['total'] ?? ''),
                'new' => $attributes['total'] ?? (isset($attributes['qty']) && isset($attributes['price']) ? ($attributes['qty'] * $attributes['price']) : '')
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
                'new' => $attributes['qty'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
            'total' => [
                'old' => '',
                'new' => $attributes['total'] ?? (($attributes['qty'] ?? 0) * ($attributes['price'] ?? 0))
            ],
        ];
    }

    private function extractBookedDeviceCreated($properties): array
    {
        $attributes = $properties['attributes'] ?? [];

        return [
            'deviceName' => [
                'old' => '',
                'new' => !empty($attributes['device_id'])
                    ? (Device::find($attributes['device_id'])?->name ?? '')
                    : ''
            ],
            'deviceType' => [
                'old' => '',
                'new' => !empty($attributes['device_type_id'])
                    ? (DeviceType::find($attributes['device_type_id'])?->name ?? '')
                    : ''
            ],
            'deviceTime' => [
                'old' => '',
                'new' => !empty($attributes['device_time_id'])
                    ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '')
                    : ''
            ],
            'status' => [
                'old' => '',
                'new' => $attributes['status'] ?? ''
            ],
        ];
    }

    private function extractBookedDeviceUpdated($properties, $bookedDeviceId = null): array
    {
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        $props = [];

        // Get the current BookedDevice to access all fields
        $bookedDevice = null;
        if ($bookedDeviceId) {
            $bookedDevice = BookedDevice::find($bookedDeviceId);
        }

        $allFields = ['device_id', 'device_type_id', 'device_time_id', 'status'];

        foreach ($allFields as $field) {
            $displayField = match($field) {
                'device_id' => 'deviceName',
                'device_type_id' => 'deviceType',
                'device_time_id' => 'deviceTime',
                'status' => 'status',
                default => $field
            };

            // Check if field changed
            $hasChanged = array_key_exists($field, $old) &&
                         array_key_exists($field, $attributes) &&
                         $old[$field] != $attributes[$field];

            if ($hasChanged) {
                // Field changed - show old and new
                $oldValue = $old[$field] ?? '';
                $newValue = $attributes[$field] ?? '';

                // Convert IDs to names
                if ($field === 'device_id') {
                    $oldValue = !empty($oldValue) ? (Device::find($oldValue)?->name ?? '') : '';
                    $newValue = !empty($newValue) ? (Device::find($newValue)?->name ?? '') : '';
                } elseif ($field === 'device_type_id') {
                    $oldValue = !empty($oldValue) ? (DeviceType::find($oldValue)?->name ?? '') : '';
                    $newValue = !empty($newValue) ? (DeviceType::find($newValue)?->name ?? '') : '';
                } elseif ($field === 'device_time_id') {
                    $oldValue = !empty($oldValue) ? (DeviceTime::find($oldValue)?->name ?? '') : '';
                    $newValue = !empty($newValue) ? (DeviceTime::find($newValue)?->name ?? '') : '';
                }

                $props[$displayField] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            } elseif ($bookedDevice) {
                // Field didn't change - get current value from BookedDevice
                $value = $bookedDevice->{$field} ?? '';

                // Convert IDs to names
                if ($field === 'device_id') {
                    $value = !empty($value) ? (Device::find($value)?->name ?? '') : '';
                } elseif ($field === 'device_type_id') {
                    $value = !empty($value) ? (DeviceType::find($value)?->name ?? '') : '';
                } elseif ($field === 'device_time_id') {
                    $value = !empty($value) ? (DeviceTime::find($value)?->name ?? '') : '';
                }

                $props[$displayField] = [
                    'old' => $value,
                    'new' => $value
                ];
            }
        }

        return $props;
    }

    private function extractBookedDeviceDeleted($properties): array
    {
        $attributes = $properties['old'] ?? [];

        return [
            'deviceName' => [
                'old' => '',
                'new' => !empty($attributes['device_id'])
                    ? (Device::find($attributes['device_id'])?->name ?? '')
                    : ''
            ],
            'deviceType' => [
                'old' => '',
                'new' => !empty($attributes['device_type_id'])
                    ? (DeviceType::find($attributes['device_type_id'])?->name ?? '')
                    : ''
            ],
            'deviceTime' => [
                'old' => '',
                'new' => !empty($attributes['device_time_id'])
                    ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '')
                    : ''
            ],
            'status' => [
                'old' => '',
                'new' => $attributes['status'] ?? ''
            ],
        ];
    }

    private function resolveResource(): array
    {
        $modelName = strtolower($this->log_name);
        $event = $this->event;

        return match ($modelName) {
            'daily' => $this->getDailyProperties($event),
            'order' => $this->getOrderProperties($event),
            'orderitem' => $this->getOrderItemProperties($event),
            'expense' => $this->getExpenseProperties($event),
            'sessiondevice' => $this->getSessionDeviceProperties($event),
            'bookeddevice' => $this->getBookedDeviceProperties($event),
            'bookeddevicepause' => $this->getBookedDevicePauseProperties($event),
            default => [],
        };
    }

    // ==================== Daily ====================
    private function getDailyProperties(string $event): array
    {
        return match($event) {
            'created' => $this->getDailyCreatedProperties(),
            'updated' => $this->getDailyUpdatedProperties(),
            'deleted' => $this->getDailyDeletedProperties(),
            default => []
        };
    }

    private function getDailyCreatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];

        return [
            'startDateTime' => [
                'old' => '',
                'new' => $attributes['start_date_time'] ?? ''
            ],
            'endDateTime' => [
                'old' => '',
                'new' => $attributes['end_date_time'] ?? ''
            ],
        ];
    }

    private function getDailyUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];

        $props = [];
        $importantFields = [
            'start_date_time' => 'startDateTime',
            'end_date_time' => 'endDateTime',
            'total_income' => 'totalIncome',
            'total_expense' => 'totalExpense',
            'total_profit' => 'totalProfit'
        ];

        foreach ($importantFields as $dbField => $responseField) {
            if (array_key_exists($dbField, $attributes) &&
                array_key_exists($dbField, $old) &&
                $old[$dbField] != $attributes[$dbField]) {

                $props[$responseField] = [
                    'old' => $old[$dbField] ?? '',
                    'new' => $attributes[$dbField] ?? ''
                ];
            }
        }

        return $props;
    }

    private function getDailyDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];

        return [
            'startDateTime' => [
                'old' => '',
                'new' => $attributes['start_date_time'] ?? ''
            ],
            'endDateTime' => [
                'old' => '',
                'new' => $attributes['end_date_time'] ?? ''
            ],
        ];
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

        $props = [
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'number' => [
                'old' => '',
                'new' => $attributes['number'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
            'deliveredStatus' => [
                'old' => '',
                'new' => $attributes['status'] ?? ''
            ],
            'payStatus' => [
                'old' => '',
                'new' => $attributes['is_paid'] ?? ''
            ],
        ];

        // Add bookedDevice info if exists
        if (!empty($attributes['booked_device_id'])) {
            $device = BookedDevice::withTrashed()->find($attributes['booked_device_id']);
            if ($device) {
                $props['bookedDevice'] = [
                    'id' => $device->id,
                    'name' => $device->device?->name ?? ''
                ];
            }
        }

        return $props;
    }

    private function getOrderUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];

        $props = [];

        // Always show number if available
        if (!empty($attributes['number'])) {
            $props['number'] = [
                'old' => $old['number'] ?? '',
                'new' => $attributes['number']
            ];
        }

        // Always show name if available
        if (!empty($attributes['name'])) {
            $props['name'] = [
                'old' => $old['name'] ?? '',
                'new' => $attributes['name']
            ];
        }

        $importantFields = [
            'price' => 'price',
            'status' => 'deliveredStatus',
            'is_paid' => 'payStatus'
        ];

        foreach ($importantFields as $dbField => $responseField) {
            if (array_key_exists($dbField, $attributes) &&
                array_key_exists($dbField, $old) &&
                $old[$dbField] != $attributes[$dbField]) {

                $props[$responseField] = [
                    'old' => $old[$dbField],
                    'new' => $attributes[$dbField]
                ];
            }
        }

        // Add bookedDevice info if exists (from attributes or old)
        $bookedDeviceId = $attributes['booked_device_id'] ?? $old['booked_device_id'] ?? null;
        if ($bookedDeviceId) {
            $device = BookedDevice::withTrashed()->find($bookedDeviceId);
            if ($device) {
                $props['bookedDevice'] = [
                    'id' => $device->id,
                    'name' => $device->device?->name ?? ''
                ];
            }
        }

        return $props;
    }

    private function getOrderDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];

        $props = [
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'number' => [
                'old' => '',
                'new' => $attributes['number'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
        ];

        // Add bookedDevice info if exists
        if (!empty($attributes['booked_device_id'])) {
            $device = BookedDevice::withTrashed()->find($attributes['booked_device_id']);
            if ($device) {
                $props['bookedDevice'] = [
                    'id' => $device->id,
                    'name' => $device->device?->name ?? ''
                ];
            }
        }

        return $props;
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
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
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
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
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
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'type' => [
                'old' => '',
                'new' => $attributes['type'] ?? ''
            ]
        ];
    }

    private function getSessionDeviceUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];

        $props = [];
        $importantFields = ['name', 'type'];

        foreach ($importantFields as $field) {
            if (array_key_exists($field, $old) &&
                array_key_exists($field, $attributes) &&
                $old[$field] != $attributes[$field]) {

                $props[$field] = [
                    'old' => $old[$field] ?? '',
                    'new' => $attributes[$field] ?? ''
                ];
            }
        }

        return $props;
    }

    private function getSessionDeviceDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];

        return [
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'type' => [
                'old' => '',
                'new' => $attributes['type'] ?? ''
            ]
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
            'deviceName' => [
                'old' => '',
                'new' => !empty($attributes['device_id'])
                    ? (Device::find($attributes['device_id'])?->name ?? '')
                    : ''
            ],
            'deviceType' => [
                'old' => '',
                'new' => !empty($attributes['device_type_id'])
                    ? (DeviceType::find($attributes['device_type_id'])?->name ?? '')
                    : ''
            ],
            'deviceTime' => [
                'old' => '',
                'new' => !empty($attributes['device_time_id'])
                    ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '')
                    : ''
            ],
            'status' => [
                'old' => '',
                'new' => $attributes['status'] ?? ''
            ],
        ];
    }

    private function getBookedDeviceUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];

        $props = [];

        // Get the current BookedDevice to access all fields
        $bookedDevice = BookedDevice::find($this->subject_id);

        $allFields = ['device_id', 'device_type_id', 'device_time_id', 'status', 'end_date_time'];

        foreach ($allFields as $field) {
            $displayField = match($field) {
                'device_id' => 'deviceName',
                'device_type_id' => 'deviceType',
                'device_time_id' => 'deviceTime',
                'status' => 'status',
                'end_date_time' => 'endTime',
                default => $field
            };

            // Check if field changed
            $hasChanged = array_key_exists($field, $old) &&
                         array_key_exists($field, $attributes) &&
                         $old[$field] != $attributes[$field];

            if ($hasChanged) {
                // Field changed - show old and new
                $oldValue = $old[$field] ?? '';
                $newValue = $attributes[$field] ?? '';

                // Convert IDs to names
                if ($field === 'device_id') {
                    $oldValue = !empty($oldValue) ? (Device::find($oldValue)?->name ?? '') : '';
                    $newValue = !empty($newValue) ? (Device::find($newValue)?->name ?? '') : '';
                } elseif ($field === 'device_type_id') {
                    $oldValue = !empty($oldValue) ? (DeviceType::find($oldValue)?->name ?? '') : '';
                    $newValue = !empty($newValue) ? (DeviceType::find($newValue)?->name ?? '') : '';
                } elseif ($field === 'device_time_id') {
                    $oldValue = !empty($oldValue) ? (DeviceTime::find($oldValue)?->name ?? '') : '';
                    $newValue = !empty($newValue) ? (DeviceTime::find($newValue)?->name ?? '') : '';
                }

                $props[$displayField] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            } elseif ($bookedDevice) {
                // Field didn't change - get current value from BookedDevice
                $value = $bookedDevice->{$field} ?? '';

                // Convert IDs to names
                if ($field === 'device_id') {
                    $value = !empty($value) ? (Device::find($value)?->name ?? '') : '';
                } elseif ($field === 'device_type_id') {
                    $value = !empty($value) ? (DeviceType::find($value)?->name ?? '') : '';
                } elseif ($field === 'device_time_id') {
                    $value = !empty($value) ? (DeviceTime::find($value)?->name ?? '') : '';
                }

                $props[$displayField] = [
                    'old' => $value,
                    'new' => $value
                ];
            }
        }

        return $props;
    }

    private function getBookedDeviceDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];

        return [
            'deviceName' => [
                'old' => '',
                'new' => !empty($attributes['device_id'])
                    ? (Device::find($attributes['device_id'])?->name ?? '')
                    : ''
            ],
            'deviceType' => [
                'old' => '',
                'new' => !empty($attributes['device_type_id'])
                    ? (DeviceType::find($attributes['device_type_id'])?->name ?? '')
                    : ''
            ],
            'deviceTime' => [
                'old' => '',
                'new' => !empty($attributes['device_time_id'])
                    ? (DeviceTime::find($attributes['device_time_id'])?->name ?? '')
                    : ''
            ],
            'status' => [
                'old' => '',
                'new' => $attributes['status'] ?? ''
            ],
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
