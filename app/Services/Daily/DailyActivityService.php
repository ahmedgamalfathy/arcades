<?php

namespace App\Services\Daily;

use App\Models\Daily\Daily;
use App\Models\Timer\BookedDevice\BookedDevice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyActivityService
{
    public function groupedActivities(int|string $dailyId): Collection
    {
        Daily::findOrFail($dailyId);

        $dailyRelatedActivities = DB::connection('tenant')
            ->table('activity_log')
            ->where('daily_id', $dailyId)
            ->get();

        $dailyOwnActivities = DB::connection('tenant')
            ->table('activity_log')
            ->where('subject_type', 'App\\Models\\Daily\\Daily')
            ->where('subject_id', $dailyId)
            ->get();

        $activities = $dailyRelatedActivities->merge($dailyOwnActivities)
            ->sortByDesc('created_at')
            ->values();

        $userIds = $activities->pluck('causer_id')->unique()->filter();
        $users = DB::connection('mysql')->table('users')
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        $allActivities = $activities->map(function ($activity) use ($users) {
            $activity->properties = json_decode($activity->properties, true);
            $activity->causerName = $users[$activity->causer_id] ?? null;

            return $activity;
        });

        return $this->groupParentChildActivities($allActivities);
    }

    private function groupParentChildActivities(Collection $activities): Collection
    {
        $grouped = collect();
        $childrenMap = [
            'order' => [],
            'sessiondevice' => [],
        ];

        foreach ($activities as $activity) {
            $modelName = strtolower($activity->log_name);

            if ($modelName === 'orderitem') {
                $orderId = $activity->properties['attributes']['order_id'] ??
                    $activity->properties['old']['order_id'] ?? null;

                if ($orderId) {
                    $childrenMap['order'][$orderId] ??= [];
                    $childrenMap['order'][$orderId][] = $activity;
                }
            } elseif ($modelName === 'bookeddevice') {
                $sessionId = $activity->properties['attributes']['session_device_id'] ??
                    $activity->properties['old']['session_device_id'] ?? null;

                if (!$sessionId && $activity->subject_id) {
                    $bookedDevice = BookedDevice::find($activity->subject_id);
                    $sessionId = $bookedDevice?->session_device_id;
                }

                if ($sessionId) {
                    $childrenMap['sessiondevice'][$sessionId] ??= [];
                    $childrenMap['sessiondevice'][$sessionId][] = $activity;
                }
            }
        }

        $processedChildren = [];

        foreach ($activities as $activity) {
            $modelName = strtolower($activity->log_name);
            $activityId = $activity->id;

            if (in_array($activityId, $processedChildren)) {
                continue;
            }

            if ($modelName === 'order') {
                $this->appendOrderActivity($grouped, $activity, $activities, $childrenMap, $processedChildren);
            } elseif ($modelName === 'sessiondevice') {
                $this->appendSessionDeviceActivity($grouped, $activity, $childrenMap, $processedChildren);
            } elseif ($modelName === 'orderitem') {
                $this->appendOrderItemActivity($grouped, $activity, $activities);
            } elseif ($modelName === 'bookeddevice') {
                $this->appendBookedDeviceActivity($grouped, $activity, $activities);
            } else {
                $activity->children = [];
                $grouped->push($activity);
            }
        }

        return $grouped;
    }

    private function appendOrderActivity(Collection $grouped, object $activity, Collection $activities, array $childrenMap, array &$processedChildren): void
    {
        $orderId = $activity->subject_id;
        $propertiesChildren = $activity->properties['children'] ?? [];

        if (!empty($propertiesChildren)) {
            $activity->children = $this->childrenFromOrderProperties($activity, $propertiesChildren);
        } else {
            $allChildren = $childrenMap['order'][$orderId] ?? [];
            $activity->children = collect($allChildren)->filter(function ($child) use ($activity) {
                $sameEvent = strtolower($child->event) === strtolower($activity->event);

                if (!$sameEvent) {
                    return false;
                }

                $parentTime = Carbon::parse($activity->created_at);
                $childTime = Carbon::parse($child->created_at);
                $timeDiff = abs($parentTime->diffInSeconds($childTime));

                return $timeDiff <= 10;
            })->values()->all();

            foreach ($activity->children as $child) {
                $processedChildren[] = $child->id;
            }
        }

        $grouped->push($activity);
    }

    private function childrenFromOrderProperties(object $activity, array $propertiesChildren): array
    {
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

            if (!$oldItem) {
                return (object) [
                    'log_name' => 'OrderItem',
                    'event' => 'created',
                    'properties' => ['attributes' => $childData],
                ];
            }

            $hasChanges = false;
            foreach (['product_id', 'qty', 'price'] as $field) {
                if (isset($oldItem[$field], $childData[$field]) && $oldItem[$field] != $childData[$field]) {
                    $hasChanges = true;
                    break;
                }
            }

            if (!$hasChanges) {
                return null;
            }

            return (object) [
                'log_name' => 'OrderItem',
                'event' => 'updated',
                'properties' => [
                    'attributes' => $childData,
                    'old' => $oldItem,
                ],
            ];
        })->filter();

        $deletedIds = $oldItemsMap->keys()->diff($newItemsMap->keys());
        foreach ($deletedIds as $deletedId) {
            $children->push((object) [
                'log_name' => 'OrderItem',
                'event' => 'deleted',
                'properties' => ['old' => $oldItemsMap->get($deletedId)],
            ]);
        }

        return $children->all();
    }

    private function appendSessionDeviceActivity(Collection $grouped, object $activity, array $childrenMap, array &$processedChildren): void
    {
        $sessionId = $activity->subject_id;
        $propertiesChildren = $activity->properties['children'] ?? [];

        if (!empty($propertiesChildren)) {
            $activity->children = collect($propertiesChildren)
                ->map(fn ($childData) => $this->childFromSessionProperties($childData))
                ->all();
        } else {
            $activity->children = collect($childrenMap['sessiondevice'][$sessionId] ?? [])->values()->all();

            foreach ($activity->children as $child) {
                $processedChildren[] = $child->id;
            }
        }

        $grouped->push($activity);
    }

    private function childFromSessionProperties(array $childData): object
    {
        $event = $childData['event'] ?? 'updated';

        if ($event === 'created' || $event === 'transfer') {
            return (object) [
                'log_name' => $childData['log_name'] ?? 'BookedDevice',
                'event' => $event,
                'subject_id' => $childData['id'] ?? null,
                'properties' => [
                    'attributes' => $this->bookedDeviceAttributes($childData),
                ],
            ];
        }

        if ($event === 'deleted') {
            return (object) [
                'log_name' => $childData['log_name'] ?? 'BookedDevice',
                'event' => $event,
                'subject_id' => $childData['id'] ?? null,
                'properties' => [
                    'old' => $this->bookedDeviceAttributes($childData),
                ],
            ];
        }

        return (object) [
            'log_name' => $childData['log_name'] ?? 'BookedDevice',
            'event' => $event,
            'subject_id' => $childData['id'] ?? null,
            'properties' => [
                'attributes' => [
                    ...$this->bookedDeviceAttributes($childData),
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

    private function bookedDeviceAttributes(array $childData): array
    {
        return [
            'device_id' => $childData['device_id'] ?? null,
            'device_type_id' => $childData['device_type_id'] ?? null,
            'device_time_id' => $childData['device_time_id'] ?? null,
            'status' => $childData['status'] ?? null,
        ];
    }

    private function appendOrderItemActivity(Collection $grouped, object $activity, Collection $activities): void
    {
        $orderId = $activity->properties['attributes']['order_id'] ??
            $activity->properties['old']['order_id'] ?? null;

        $parentOrder = $activities->first(function ($parent) use ($orderId) {
            return strtolower($parent->log_name) === 'order' && $parent->subject_id == $orderId;
        });

        if (!$parentOrder) {
            $this->pushStandaloneChild($grouped, $activity, 'Order', $orderId);
            return;
        }

        $parentTime = Carbon::parse($parentOrder->created_at);
        $childTime = Carbon::parse($activity->created_at);
        $timeDiff = abs($parentTime->diffInSeconds($childTime));
        $sameEvent = strtolower($activity->event) === strtolower($parentOrder->event);

        if (!$sameEvent || $timeDiff > 10) {
            $this->pushStandaloneChild($grouped, $activity, 'Order', $orderId);
        }
    }

    private function appendBookedDeviceActivity(Collection $grouped, object $activity, Collection $activities): void
    {
        $sessionId = $activity->properties['attributes']['session_device_id'] ??
            $activity->properties['old']['session_device_id'] ?? null;

        if (!$sessionId && $activity->subject_id) {
            $bookedDevice = BookedDevice::find($activity->subject_id);
            $sessionId = $bookedDevice?->session_device_id;
        }

        $parentSession = $activities->first(function ($parent) use ($sessionId) {
            return strtolower($parent->log_name) === 'sessiondevice' && $parent->subject_id == $sessionId;
        });

        if (!$parentSession) {
            $this->pushStandaloneChild($grouped, $activity, 'SessionDevice', $sessionId);
        }
    }

    private function pushStandaloneChild(Collection $grouped, object $activity, string $modelName, int|string|null $modelId): void
    {
        $activity->children = [];

        if ($modelId) {
            $activity->parentInfo = [
                'modelName' => $modelName,
                'modelId' => $modelId,
            ];
        }

        $grouped->push($activity);
    }
}
