<?php

namespace App\Services\Timer\Concerns;

use App\Models\Order\Order;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use Spatie\Activitylog\Models\Activity;

trait HandlesBookedDeviceActivityLog
{
    public function getActivityLogToDevice($bookedDeviceId)
    {
        $currentBookedDevice = BookedDevice::findOrFail($bookedDeviceId);
        $deviceId = $currentBookedDevice->device_id;
        $timerId = $this->getTimerId($currentBookedDevice);

        $activities = Activity::where(function ($query) use ($timerId) {
            $query->whereJsonContains('properties->timer_id', $timerId)
                ->whereDate('created_at', today());
        })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($activities->isEmpty()) {
            $activities = $this->getCurrentDeviceActivities($currentBookedDevice);
        }

        $deviceSessionKey = $this->findCurrentSessionKey($currentBookedDevice);
        $activities = $activities->map(function ($activity) use ($deviceSessionKey, $deviceId, $timerId, $currentBookedDevice) {
            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            $properties['device_session_key'] = $deviceSessionKey;
            $properties['device_id'] = $deviceId;
            $properties['timer_id'] = $timerId;
            $properties['current_session_only'] = true;

            if ($activity->subject_type === Order::class) {
                $properties['booked_device_id'] = $currentBookedDevice->id;
                $properties['related_to_device'] = true;
            }

            $activity->properties = $properties;

            return $activity;
        });

        $bookedDeviceIds = [$currentBookedDevice->id];
        $sessionIds = $currentBookedDevice->session_device_id ? [$currentBookedDevice->session_device_id] : [];
        $orderIds = Order::where('booked_device_id', $currentBookedDevice->id)
            ->whereDate('created_at', today())
            ->pluck('id')
            ->toArray();

        return $this->groupParentChildActivitiesForDevice($activities, $bookedDeviceIds, $orderIds, $sessionIds, $deviceId);
    }

    private function getCurrentDeviceActivities(BookedDevice $currentBookedDevice)
    {
        $allSessionIds = Activity::where('subject_type', SessionDevice::class)
            ->whereDate('created_at', today())
            ->whereJsonContains('properties->device_id', $currentBookedDevice->device_id)
            ->pluck('subject_id')
            ->unique()
            ->toArray();

        if ($currentBookedDevice->session_device_id && ! in_array($currentBookedDevice->session_device_id, $allSessionIds)) {
            $allSessionIds[] = $currentBookedDevice->session_device_id;
        }

        return Activity::where(function ($query) use ($currentBookedDevice, $allSessionIds) {
            $query->where(function ($q) use ($currentBookedDevice) {
                $q->where('subject_type', BookedDevice::class)
                    ->where('subject_id', $currentBookedDevice->id);
            })
                ->orWhere(function ($q) use ($allSessionIds) {
                    if (! empty($allSessionIds)) {
                        $q->where('subject_type', SessionDevice::class)
                            ->whereIn('subject_id', $allSessionIds);
                    }
                })
                ->orWhere(function ($q) use ($currentBookedDevice) {
                    $q->where('subject_type', Order::class)
                        ->whereIn('subject_id', function ($orderQuery) use ($currentBookedDevice) {
                            $orderQuery->select('id')
                                ->from('orders')
                                ->where('booked_device_id', $currentBookedDevice->id)
                                ->whereDate('created_at', today());
                        });
                });
        })
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function groupParentChildActivitiesForDevice($activities, $bookedDeviceIds, $orderIds, $sessionIds, $deviceId = null)
    {
        $grouped = collect();
        $processedChildren = [];

        foreach ($activities as $activity) {
            $modelName = strtolower($activity->log_name);
            $activityId = $activity->id;

            if (in_array($activityId, $processedChildren)) {
                continue;
            }

            if ($modelName === 'order') {
                $activity->children = $this->buildOrderChildren($activity);
                $grouped->push($activity);

                continue;
            }

            if ($modelName === 'sessiondevice') {
                $activity->children = $this->buildSessionDeviceChildren($activity, $activities, $bookedDeviceIds, $deviceId, $processedChildren);

                if (! empty($activity->children)) {
                    $grouped->push($activity);
                }

                continue;
            }

            if ($modelName === 'bookeddevice' && in_array($activity->subject_id, $bookedDeviceIds)) {
                continue;
            }

            $activity->children = [];
            $grouped->push($activity);
        }

        return $grouped;
    }

    private function buildOrderChildren($activity): array
    {
        $propertiesChildren = $activity->properties['children'] ?? [];

        if (empty($propertiesChildren)) {
            return [];
        }

        if ($activity->event !== 'updated') {
            return collect($propertiesChildren)->map(function ($childData) use ($activity) {
                $properties = $activity->event === 'deleted'
                    ? ['old' => $childData]
                    : ['attributes' => $childData];

                return (object) [
                    'log_name' => 'OrderItem',
                    'event' => $activity->event,
                    'properties' => $properties,
                ];
            })->all();
        }

        $oldItems = $activity->properties['old']['items'] ?? [];
        $oldItemsMap = collect($oldItems)->keyBy('id');
        $newItemsMap = collect($propertiesChildren)->keyBy('id');

        $children = collect($propertiesChildren)->map(function ($childData) use ($oldItemsMap) {
            $itemId = $childData['id'] ?? null;
            $oldItem = $oldItemsMap->get($itemId);

            if (! $oldItem) {
                return (object) [
                    'log_name' => 'OrderItem',
                    'event' => 'created',
                    'properties' => ['attributes' => $childData],
                ];
            }

            foreach (['product_id', 'qty', 'price'] as $field) {
                if (isset($oldItem[$field]) && isset($childData[$field]) && $oldItem[$field] != $childData[$field]) {
                    return (object) [
                        'log_name' => 'OrderItem',
                        'event' => 'updated',
                        'properties' => ['attributes' => $childData, 'old' => $oldItem],
                    ];
                }
            }

            return null;
        })->filter();

        foreach ($oldItemsMap->keys()->diff($newItemsMap->keys()) as $deletedId) {
            $children->push((object) [
                'log_name' => 'OrderItem',
                'event' => 'deleted',
                'properties' => ['old' => $oldItemsMap->get($deletedId)],
            ]);
        }

        return $children->all();
    }

    private function buildSessionDeviceChildren($activity, $activities, array $bookedDeviceIds, $deviceId, array &$processedChildren): array
    {
        $propertiesChildren = $activity->properties['children'] ?? [];

        if (! empty($propertiesChildren)) {
            return collect($propertiesChildren)
                ->filter(function ($childData) use ($deviceId, $bookedDeviceIds) {
                    if ($deviceId === null) {
                        return true;
                    }

                    $childDeviceId = $childData['device_id'] ?? null;
                    $childId = $childData['id'] ?? null;

                    return ($childDeviceId == $deviceId) || in_array($childId, $bookedDeviceIds);
                })
                ->map(fn ($childData) => $this->makeBookedDeviceChildActivity($childData))
                ->values()
                ->all();
        }

        $children = collect($activities)->filter(function ($child) use ($activity, $bookedDeviceIds) {
            if (strtolower($child->log_name) !== 'bookeddevice') {
                return false;
            }

            if (! in_array($child->subject_id, $bookedDeviceIds)) {
                return false;
            }

            if (strtolower($child->event) !== strtolower($activity->event)) {
                return false;
            }

            $parentTime = \Carbon\Carbon::parse($activity->created_at);
            $childTime = \Carbon\Carbon::parse($child->created_at);

            return abs($parentTime->diffInSeconds($childTime)) <= 30;
        })->values();

        foreach ($children as $child) {
            $processedChildren[] = $child->id;
        }

        return $children->all();
    }

    private function makeBookedDeviceChildActivity(array $childData): object
    {
        $event = $childData['event'] ?? 'updated';

        if ($event === 'created' || $event === 'transfer') {
            return (object) [
                'log_name' => $childData['log_name'] ?? 'BookedDevice',
                'event' => $event,
                'subject_id' => $childData['id'] ?? null,
                'properties' => [
                    'attributes' => [
                        'device_id' => $childData['device_id'] ?? null,
                        'device_type_id' => $childData['device_type_id'] ?? null,
                        'device_time_id' => $childData['device_time_id'] ?? null,
                        'status' => $childData['status'] ?? null,
                    ],
                ],
            ];
        }

        if ($event === 'deleted') {
            return (object) [
                'log_name' => $childData['log_name'] ?? 'BookedDevice',
                'event' => $event,
                'subject_id' => $childData['id'] ?? null,
                'properties' => [
                    'old' => [
                        'device_id' => $childData['device_id'] ?? null,
                        'device_type_id' => $childData['device_type_id'] ?? null,
                        'device_time_id' => $childData['device_time_id'] ?? null,
                        'status' => $childData['status'] ?? null,
                    ],
                ],
            ];
        }

        return (object) [
            'log_name' => $childData['log_name'] ?? 'BookedDevice',
            'event' => $event,
            'subject_id' => $childData['id'] ?? null,
            'properties' => [
                'attributes' => [
                    'device_id' => $childData['device_id'] ?? null,
                    'device_type_id' => $childData['device_type_id'] ?? null,
                    'device_time_id' => $childData['device_time_id'] ?? null,
                    'status' => $childData['status'] ?? null,
                    'end_date_time' => $childData['end_date_time'] ?? null,
                ],
                'old' => [
                    'device_id' => $childData['device_id'] ?? null,
                    'device_type_id' => $childData['device_type_id'] ?? null,
                    'device_time_id' => $childData['old_device_time_id'] ?? $childData['device_time_id'] ?? null,
                    'status' => $childData['old_status'] ?? null,
                    'old_end_date_time' => $childData['old_end_date_time'] ?? null,
                ],
            ],
        ];
    }

    private function findCurrentSessionKey($bookedDevice)
    {
        $dailyId = null;

        if ($bookedDevice->session_device_id) {
            $sessionDevice = SessionDevice::find($bookedDevice->session_device_id);
            if ($sessionDevice) {
                $dailyId = $sessionDevice->daily_id;
            }
        }

        $deviceStartDate = $bookedDevice->created_at->format('Y-m-d');

        return 'device_'.$bookedDevice->device_id.'_daily_'.($dailyId ?? 'unknown').'_'.$deviceStartDate;
    }

    private function getTimerId($bookedDevice)
    {
        $existingActivity = Activity::where('subject_type', BookedDevice::class)
            ->where('subject_id', $bookedDevice->id)
            ->orderBy('created_at', 'asc')
            ->first();

        if ($existingActivity) {
            $properties = is_string($existingActivity->properties)
                ? json_decode($existingActivity->properties, true)
                : ($existingActivity->properties ?? []);

            if (isset($properties['timer_id'])) {
                return $properties['timer_id'];
            }
        }

        return 'timer_'.$bookedDevice->device_id.'_'.$bookedDevice->created_at->timestamp;
    }

    private function groupActivitiesBySessionKey($activities, $sessionKey)
    {
        return $activities->map(function ($activity) use ($sessionKey) {
            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            $properties['session_key'] = $sessionKey;
            $properties['session_group'] = true;

            $activity->properties = $properties;

            return $activity;
        });
    }
}
