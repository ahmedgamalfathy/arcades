<?php

namespace App\Http\Resources\ActivityLog\Test\Concerns;

use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;

trait ResolvesActivityModels
{
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

        // Always show price if available
        if (array_key_exists('price', $attributes)) {
            $props['price'] = [
                'old' => $old['price'] ?? '',
                'new' => $attributes['price']
            ];
        }

        // Show status and is_paid only if changed
        $conditionalFields = [
            'status' => 'deliveredStatus',
            'is_paid' => 'payStatus'
        ];

        foreach ($conditionalFields as $dbField => $responseField) {
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
        $type = $attributes['type'] ?? 0;

        $props = [
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
        ];

        // Add date and note only for external expenses (type = 1)
        if ($type == 1) {
            $props['date'] = [
                'old' => '',
                'new' => $attributes['date'] ?? ''
            ];
            $props['note'] = [
                'old' => '',
                'new' => $attributes['note'] ?? ''
            ];
        }

        return $props;
    }

    private function getExpenseUpdatedProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];

        $props = [];

        // Get the Expense to access current values if not in attributes (including soft deleted)
        $expense = null;
        if ($this->subject_id) {
            $expense = \App\Models\Expense\Expense::withTrashed()->find($this->subject_id);
        }

        $type = $attributes['type'] ?? $old['type'] ?? ($expense ? $expense->type : 0);

        // Always show name
        if (!empty($attributes['name'])) {
            $props['name'] = [
                'old' => $old['name'] ?? '',
                'new' => $attributes['name']
            ];
        } elseif ($expense) {
            $props['name'] = [
                'old' => '',
                'new' => $expense->name
            ];
        }

        // Always show price
        if (array_key_exists('price', $attributes)) {
            $props['price'] = [
                'old' => $old['price'] ?? '',
                'new' => $attributes['price']
            ];
        } elseif ($expense) {
            $props['price'] = [
                'old' => '',
                'new' => $expense->price
            ];
        }

        // Show date and note only for external expenses (type = 1)
        if ($type == 1) {
            // Always show date
            if (array_key_exists('date', $attributes)) {
                $props['date'] = [
                    'old' => $old['date'] ?? '',
                    'new' => $attributes['date']
                ];
            } elseif ($expense) {
                $props['date'] = [
                    'old' => '',
                    'new' => $expense->date
                ];
            }

            // Always show note
            if (array_key_exists('note', $attributes)) {
                $props['note'] = [
                    'old' => $old['note'] ?? '',
                    'new' => $attributes['note'] ?? ''
                ];
            } elseif ($expense) {
                $props['note'] = [
                    'old' => '',
                    'new' => $expense->note ?? ''
                ];
            }
        }

        return $props;
    }

    private function getExpenseDeletedProperties(): array
    {
        $attributes = $this->properties['old'] ?? [];
        $type = $attributes['type'] ?? 0;

        $props = [
            'name' => [
                'old' => '',
                'new' => $attributes['name'] ?? ''
            ],
            'price' => [
                'old' => '',
                'new' => $attributes['price'] ?? ''
            ],
        ];

        // Add date and note only for external expenses (type = 1)
        if ($type == 1) {
            $props['date'] = [
                'old' => '',
                'new' => $attributes['date'] ?? ''
            ];
            $props['note'] = [
                'old' => '',
                'new' => $attributes['note'] ?? ''
            ];
        }

        return $props;
    }

    // ==================== SessionDevice ====================
    private function getSessionDeviceProperties(string $event): array
    {
        return match($event) {
            'created' => $this->getSessionDeviceCreatedProperties(),
            'updated' => $this->getSessionDeviceUpdatedProperties(),
            'deleted' => $this->getSessionDeviceDeletedProperties(),
            'transfer' => $this->getSessionDeviceTransferProperties(),
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
            if (array_key_exists($field, $attributes)) {
                // Check if field changed
                if (array_key_exists($field, $old) && $old[$field] != $attributes[$field]) {
                    // Field changed - show old and new
                    $props[$field] = [
                        'old' => $old[$field] ?? '',
                        'new' => $attributes[$field] ?? ''
                    ];
                } else {
                    // Field didn't change - show with old=""
                    $props[$field] = [
                        'old' => '',
                        'new' => $attributes[$field] ?? ''
                    ];
                }
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

    private function getSessionDeviceTransferProperties(): array
    {
        $attributes = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];

        return [
            'name' => [
                'old' => $old['name'] ?? '',
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
