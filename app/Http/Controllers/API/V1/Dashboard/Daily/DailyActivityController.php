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
        $allDailys=Activity::where('subject_type', Daily::class)
        ->where('subject_id', $dailyId)
        ->orderBy('created_at', 'desc')
        ->get();
        $allActivities = Activity::where('daily_id', $dailyId)
            ->orderBy('created_at', 'asc')
            ->get();

        $activities = [
            'daily' => $allDailys,
            'sessions' => $allActivities->where('subject_type', SessionDevice::class)->values(),
            'orders' => $allActivities->where('subject_type', Order::class)->values(),
            'orderItems' => $allActivities->where('subject_type', OrderItem::class)->values(),
            'expenses' => $allActivities->where('subject_type', Expense::class)->values(),
            'bookedDevices' => $allActivities->where('subject_type', BookedDevice::class)->values(),
        ];          
         return ApiResponse::success(new AllActionDailyActivityResource($activities));

    }
}
