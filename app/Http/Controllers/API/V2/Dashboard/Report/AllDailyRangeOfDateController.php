<?php

namespace App\Http\Controllers\API\V2\Dashboard\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\Daily\V2\AllDailyRangeOfDateResource;
use Illuminate\Http\Request;
use App\Services\Daily\DailyService;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
class AllDailyRangeOfDateController extends Controller implements HasMiddleware
{
    /**
     * Handle the incoming request.
     */
    public function __construct(protected DailyService $dailyService)
    {
        $this->dailyService = $dailyService;
    }
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('tenant'),
        ];
    }
    public function __invoke(Request $request)
    {
        $dailies = $this->dailyService->allDailiesWithOutDailyId($request);
        return ApiResponse::success(
            new AllDailyRangeOfDateResource($dailies)
        );

    }
}
