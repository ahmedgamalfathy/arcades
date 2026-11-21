<?php

namespace App\Http\Controllers\API\V1\Dashboard\Report;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\CreateReportRequest;
use App\Services\Report\DailyReportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class ReportController extends Controller
{
    private function dailyReportService(): DailyReportService
    {
        return app(DailyReportService::class);
    }

    // reports?startDateTime=2025-10-26&endDateTime=2025-10-26&include=orders,expenses,devices
    public function getReport(CreateReportRequest $createReportRequest)
    {

        try {
            $report = $this->dailyReportService()->getReport($createReportRequest->validated());

            return ApiResponse::success($report);
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function getStatusReport(CreateReportRequest $createReportRequest)
    {
        try {
            $report = $this->dailyReportService()->getStatusReport($createReportRequest->validated());
            $report = collect($report)->map(fn ($item) => (object) $item);

            return ApiResponse::success($report);
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function getExpensesReport(CreateReportRequest $createReportRequest)
    {
        try {
            $data = $createReportRequest->validated();
            $data['include'] = 'expenses';

            return ApiResponse::success($this->dailyReportService()->getReport($data));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
}
