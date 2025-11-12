<?php

namespace App\Http\Controllers\API\V1\Dashboard\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Report\DailyReportService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Report\CreateReportRequest;
use App\Services\Report\DailyReportStatusService;
use Carbon\Carbon;
use App\Models\Order\Order;
use App\Models\Expense\Expense;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Daily\Daily;
use App\Enums\Expense\ExpenseTypeEnum;
use Throwable;
class DailyReportStatusController extends Controller implements HasMiddleware
{
       protected $dailyReportStatusService;


    public function __construct(DailyReportStatusService $dailyReportStatusService)
    {
        $this->dailyReportStatusService = $dailyReportStatusService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('permission:products', only:['index']),
            new Middleware('permission:create_products', only:['create']),
            new Middleware('permission:edit_product', only:['edit']),
            new Middleware('permission:update_product', only:['update']),
            new Middleware('permission:destroy_product', only:['destroy']),
            new Middleware('tenant'),
        ];
    }  
    public function getStatusReport(CreateReportRequest $createReportRequest)
    {
        try {
            $report= $this->dailyReportStatusService->reports($createReportRequest->validated());
            // $report = collect($report)->map(fn($item) => (object) $item);
            return ApiResponse::success($report);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

}