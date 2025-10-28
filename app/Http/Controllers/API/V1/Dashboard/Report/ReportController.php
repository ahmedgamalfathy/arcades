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
use App\Http\Resources\Daily\Report\AllReportDailyResource;
use Throwable;
class ReportController extends Controller implements HasMiddleware
{
       protected $dailyReportService;


    public function __construct(DailyReportService $dailyReportService)
    {
        $this->dailyReportService = $dailyReportService;
    }

    public static function middleware(): array
    {
        return [//products , create_products,edit_product,update_product ,destroy_product
            new Middleware('auth:api'),
            // new Middleware('permission:daily', only:['index']),
            // new Middleware('permission:create_daily', only:['create']),
            // new Middleware('permission:edit_daily', only:['edit']),
            // new Middleware('permission:update_daily', only:['update']),
            // new Middleware('permission:destroy_daily', only:['destroy']),
            new Middleware('permission:products', only:['index']),
            new Middleware('permission:create_products', only:['create']),
            new Middleware('permission:edit_product', only:['edit']),
            new Middleware('permission:update_product', only:['update']),
            new Middleware('permission:destroy_product', only:['destroy']),
            new Middleware('tenant'),
        ];
    }  
    //reports?startDateTime=2025-10-26&endDateTime=2025-10-26&include=orders,expenses,devices
    public function getReport(CreateReportRequest $createReportRequest)
    {
        try {
            $report= $this->dailyReportService->getReport($createReportRequest->validated());
            return ApiResponse::success($report);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function getStatusReport(CreateReportRequest $createReportRequest)
    {
        try {
            $report= $this->dailyReportService->getStatusReport($createReportRequest->validated());
            $report = collect($report)->map(fn($item) => (object) $item);
            
            return ApiResponse::success(AllReportDailyResource::collection($report));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

}
