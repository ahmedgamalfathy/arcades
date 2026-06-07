<?php

namespace App\Http\Controllers\API\V2\Dashboard\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\Daily\V2\AllDailyRangeOfDateResource;
use Illuminate\Http\Request;
use App\Services\Daily\DailyService;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
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
