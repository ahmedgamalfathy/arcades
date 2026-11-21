<?php

namespace App\Http\Controllers\API\V2\Dashboard\Report;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Daily\V2\AllDailyRangeOfDateResource;
use App\Services\Daily\DailyService;
use Illuminate\Http\Request;

class AllDailyRangeOfDateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __construct(protected DailyService $dailyService)
    {
        $this->dailyService = $dailyService;
    }

    public function __invoke(Request $request)
    {
        $dailies = $this->dailyService->allDailiesWithOutDailyId($request);

        return ApiResponse::success(
            new AllDailyRangeOfDateResource($dailies)
        );

    }
}
