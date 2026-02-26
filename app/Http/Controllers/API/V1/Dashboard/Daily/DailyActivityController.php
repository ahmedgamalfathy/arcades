<?php

namespace App\Http\Controllers\API\V1\Dashboard\Daily;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Daily\Daily;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Expense\Expense;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Http\Resources\ActivityLog\AllActionDailyActivityResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Helpers\ApiResponse;
use Illuminate\Validation\Rules\Unique;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;
use Illuminate\Support\Facades\Log;

class DailyActivityController extends Controller  implements HasMiddleware
{
    /**
     * Handle the incoming request.
     */
        public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('tenant'),
        ];
    }
public function __invoke(Request $request)
{
    $dailyId = $request->query('dailyId');
    Daily::findOrFail($dailyId);

    // Get activities related to this daily
    // 1. Activities with daily_id = $dailyId (Orders, Expenses, Sessions, etc.)
    $dailyRelatedActivities = DB::connection('tenant')
        ->table('activity_log')
        ->where('daily_id', $dailyId)
        ->get();

    // 2. Activities for the Daily itself (subject_type = Daily, subject_id = $dailyId)
    $dailyOwnActivities = DB::connection('tenant')
        ->table('activity_log')
        ->where('subject_type', 'App\\Models\\Daily\\Daily')
        ->where('subject_id', $dailyId)
        ->get();

    // Merge both collections
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

    // Group activities by parent-child relationship
    $groupedActivities = $this->groupParentChildActivities($allActivities);

    return ApiResponse::success(AllDailyActivityResource::collection($groupedActivities));
}

private function groupParentChildActivities($activities)
{
    $grouped = collect();
    $childrenMap = [
        'order' => [],
        'sessiondevice' => []
    ];

    // First pass: identify ALL children for each parent
    foreach ($activities as $activity) {
        $modelName = strtolower($activity->log_name);

        if ($modelName === 'orderitem') {
            $orderId = $activity->properties['attributes']['order_id'] ??
                       $activity->properties['old']['order_id'] ?? null;
            if ($orderId) {
                if (!isset($childrenMap['order'][$orderId])) {
                    $childrenMap['order'][$orderId] = [];
                }
                $childrenMap['order'][$orderId][] = $activity;
            }
        } elseif ($modelName === 'bookeddevice') {
            // Try to get session_device_id from properties first
            $sessionId = $activity->properties['attributes']['session_device_id'] ??
                         $activity->properties['old']['session_device_id'] ?? null;

            // If not in properties (e.g., update that doesn't change session_device_id),
            // get it from the actual BookedDevice record
            if (!$sessionId && $activity->subject_id) {
                $bookedDevice = BookedDevice::find($activity->subject_id);
                $sessionId = $bookedDevice?->session_device_id;
            }

            if ($sessionId) {
                if (!isset($childrenMap['sessiondevice'][$sessionId])) {
                    $childrenMap['sessiondevice'][$sessionId] = [];
                }
                $childrenMap['sessiondevice'][$sessionId][] = $activity;
            }
        }
    }

    // Second pass: group parents with children
    $processedChildren = [];

    foreach ($activities as $activity) {
        $modelName = strtolower($activity->log_name);
        $activityId = $activity->id;

        // Skip if already processed as a child
        if (in_array($activityId, $processedChildren)) {
            continue;
        }

        if ($modelName === 'order') {
            $orderId = $activity->subject_id;

            // Check if this Order uses LogBatch (has children in properties)
            $propertiesChildren = $activity->properties['children'] ?? [];

            if (!empty($propertiesChildren)) {
                // LogBatch system: children are already in properties
                // Convert properties children to objects for resource processing

                if ($activity->event === 'updated') {
                    // For updates, we need to compare old and new values
                    $oldItems = $activity->properties['old']['items'] ?? [];

                    // Create a map of old items by ID for quick lookup
                    $oldItemsMap = collect($oldItems)->keyBy('id');
                    $newItemsMap = collect($propertiesChildren)->keyBy('id');

                    // Process existing and new items
                    $children = collect($propertiesChildren)->map(function($childData) use ($activity, $oldItemsMap) {
                        $itemId = $childData['id'] ?? null;
                        $oldItem = $oldItemsMap->get($itemId);

                        // If this is a new item
                        if (!$oldItem) {
                            return (object)[
                                'log_name' => 'OrderItem',
                                'event' => 'created',
                                'properties' => [
                                    'attributes' => $childData
                                ]
                            ];
                        }

                        // Check if item actually changed
                        $hasChanges = false;
                        $importantFields = ['product_id', 'qty', 'price'];

                        foreach ($importantFields as $field) {
                            if (isset($oldItem[$field]) && isset($childData[$field])) {
                                if ($oldItem[$field] != $childData[$field]) {
                                    $hasChanges = true;
                                    break;
                                }
                            }
                        }

                        // Only include if there are actual changes
                        if ($hasChanges) {
                            return (object)[
                                'log_name' => 'OrderItem',
                                'event' => 'updated',
                                'properties' => [
                                    'attributes' => $childData,
                                    'old' => $oldItem
                                ]
                            ];
                        }

                        // Return null for unchanged items (will be filtered out)
                        return null;
                    })->filter(); // Remove null values

                    // Find deleted items (in old but not in new)
                    $oldIds = $oldItemsMap->keys();
                    $newIds = $newItemsMap->keys();
                    $deletedIds = $oldIds->diff($newIds);

                    // Add deleted items to children
                    foreach ($deletedIds as $deletedId) {
                        $deletedItem = $oldItemsMap->get($deletedId);
                        $children->push((object)[
                            'log_name' => 'OrderItem',
                            'event' => 'deleted',
                            'properties' => [
                                'old' => $deletedItem
                            ]
                        ]);
                    }

                    $activity->children = $children->all();
                } else {
                    // For create/delete, just use the children as-is
                    $activity->children = collect($propertiesChildren)->map(function($childData) use ($activity) {
                        $properties = [];

                        if ($activity->event === 'deleted') {
                            // For delete, children data is the old values
                            $properties = ['old' => $childData];
                        } else {
                            // For create, children data is the new values
                            $properties = ['attributes' => $childData];
                        }

                        return (object)[
                            'log_name' => 'OrderItem',
                            'event' => $activity->event,
                            'properties' => $properties
                        ];
                    })->all();
                }
            } else {
                // Legacy system: look for separate OrderItem activities
                $allChildren = $childrenMap['order'][$orderId] ?? [];
                $activity->children = collect($allChildren)->filter(function($child) use ($activity) {
                    // Must have same event type
                    $sameEvent = strtolower($child->event) === strtolower($activity->event);
                    if (!$sameEvent) {
                        return false;
                    }

                    // Children must be within 10 seconds of parent
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

        } elseif ($modelName === 'sessiondevice') {
            $sessionId = $activity->subject_id;

            // Check if this SessionDevice uses children in properties (pause/resume/finish actions)
            $propertiesChildren = $activity->properties['children'] ?? [];

            if (!empty($propertiesChildren)) {
                // Children are in properties - convert to objects for resource processing
                $activity->children = collect($propertiesChildren)->map(function($childData) {
                    return (object)[
                        'log_name' => $childData['log_name'] ?? 'BookedDevice',
                        'event' => $childData['event'] ?? 'updated',
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
                            ]
                        ]
                    ];
                })->all();
            } else {
                // Legacy system: look for separate BookedDevice activities
                $allChildren = $childrenMap['sessiondevice'][$sessionId] ?? [];
                $activity->children = collect($allChildren)->values()->all();

                foreach ($activity->children as $child) {
                    $processedChildren[] = $child->id;
                }
            }

            $grouped->push($activity);

        } elseif ($modelName === 'orderitem') {
            $orderId = $activity->properties['attributes']['order_id'] ??
                       $activity->properties['old']['order_id'] ?? null;

            // Check if parent order exists in this daily's activities
            $parentOrder = $activities->first(function($a) use ($orderId) {
                return strtolower($a->log_name) === 'order' && $a->subject_id == $orderId;
            });

            if ($parentOrder) {
                // Parent exists in activities - check if this item should appear with parent
                $parentTime = Carbon::parse($parentOrder->created_at);
                $childTime = Carbon::parse($activity->created_at);
                $timeDiff = abs($parentTime->diffInSeconds($childTime));
                $sameEvent = strtolower($activity->event) === strtolower($parentOrder->event);

                // If NOT same event or NOT within time window, show separately
                if (!$sameEvent || $timeDiff > 10) {
                    $activity->children = [];
                    $activity->parentInfo = [
                        'modelName' => 'Order',
                        'modelId' => $orderId,
                    ];
                    $grouped->push($activity);
                }
                // Otherwise it will be shown as child of parent
            } else {
                // Parent not in activities - show standalone with parent ID
                $activity->children = [];
                if ($orderId) {
                    $activity->parentInfo = [
                        'modelName' => 'Order',
                        'modelId' => $orderId,
                    ];
                }
                $grouped->push($activity);
            }

        } elseif ($modelName === 'bookeddevice') {
            // Try to get session_device_id from properties first
            $sessionId = $activity->properties['attributes']['session_device_id'] ??
                         $activity->properties['old']['session_device_id'] ?? null;

            // If not in properties (e.g., update that doesn't change session_device_id),
            // get it from the actual BookedDevice record
            if (!$sessionId && $activity->subject_id) {
                $bookedDevice = BookedDevice::find($activity->subject_id);
                $sessionId = $bookedDevice?->session_device_id;
            }

            // Check if parent session exists in this daily's activities
            $parentSession = $activities->first(function($a) use ($sessionId) {
                return strtolower($a->log_name) === 'sessiondevice' && $a->subject_id == $sessionId;
            });

            if ($parentSession) {
                // Parent exists - BookedDevice will ALWAYS be shown as child (no separate display)
                // Do nothing - it will be shown as child of parent
            } else {
                // Parent not in activities - show standalone with parent ID
                $activity->children = [];
                if ($sessionId) {
                    $activity->parentInfo = [
                        'modelName' => 'SessionDevice',
                        'modelId' => $sessionId,
                    ];
                }
                $grouped->push($activity);
            }

        } else {
            $activity->children = [];
            $grouped->push($activity);
        }
    }

    return $grouped;
}
}
