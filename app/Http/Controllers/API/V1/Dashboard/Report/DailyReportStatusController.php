<?php

namespace App\Http\Controllers\API\V1\Dashboard\Report;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\CreateReportRequest;
use App\Services\Report\DailyReportStatusService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class DailyReportStatusController extends Controller
{
    protected $dailyReportStatusService;

    public function __construct(DailyReportStatusService $dailyReportStatusService)
    {
        $this->dailyReportStatusService = $dailyReportStatusService;
    }

    public function getStatusReport(CreateReportRequest $createReportRequest)
    {
        try {
            $report = $this->dailyReportStatusService->getStatusReport($createReportRequest->validated());

            // $report = collect($report)->map(fn($item) => (object) $item);
            return ApiResponse::success($report);
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
}
