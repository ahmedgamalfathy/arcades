<?php

namespace App\Http\Controllers\API\V1\Dashboard\Daily;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;
use App\Services\Daily\DailyActivityService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class DailyActivityController extends Controller implements HasMiddleware
{
    public function __construct(private DailyActivityService $dailyActivityService)
    {
    }

    public static function middleware(): array
    {
        return [];
    }

    public function __invoke(Request $request)
    {
        $groupedActivities = $this->dailyActivityService->groupedActivities($request->query('dailyId'));

        return ApiResponse::success(AllDailyActivityResource::collection($groupedActivities));
    }
}
