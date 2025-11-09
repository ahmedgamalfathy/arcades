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

    $activities = DB::connection('tenant')
        ->table('activity_log')
        ->where('daily_id', $dailyId)
        ->orderBy('created_at', 'desc')
        ->get();
        
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
            $sessionId = $activity->properties['attributes']['session_device_id'] ?? 
                         $activity->properties['old']['session_device_id'] ?? null;
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
            
            // Get all potential children and filter by event and time
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
            
            $grouped->push($activity);
            
        } elseif ($modelName === 'sessiondevice') {
            $sessionId = $activity->subject_id;
            
            // Get all potential children and filter by event and time
            $allChildren = $childrenMap['sessiondevice'][$sessionId] ?? [];
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
            $sessionId = $activity->properties['attributes']['session_device_id'] ?? 
                         $activity->properties['old']['session_device_id'] ?? null;
            
            // Check if parent session exists in this daily's activities
            $parentSession = $activities->first(function($a) use ($sessionId) {
                return strtolower($a->log_name) === 'sessiondevice' && $a->subject_id == $sessionId;
            });
            
            if ($parentSession) {
                // Parent exists in activities - check if this device should appear with parent
                $parentTime = Carbon::parse($parentSession->created_at);
                $childTime = Carbon::parse($activity->created_at);
                $timeDiff = abs($parentTime->diffInSeconds($childTime));
                $sameEvent = strtolower($activity->event) === strtolower($parentSession->event);
                
                // If NOT same event or NOT within time window, show separately
                if (!$sameEvent || $timeDiff > 10) {
                    $activity->children = [];
                    $activity->parentInfo = [
                        'modelName' => 'SessionDevice',
                        'modelId' => $sessionId,
                    ];
                    $grouped->push($activity);
                }
                // Otherwise it will be shown as child of parent
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
