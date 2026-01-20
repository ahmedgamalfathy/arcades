<?php

namespace App\Http\Controllers\API\V1\Dashboard\Daily;

use Throwable;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Daily\DailyService;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Services\Daily\DailyReportService;
use App\Http\Resources\Daily\AllDailyResource;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Daily\CreateDailyRequest;
use App\Http\Requests\Daily\UpdateDailyRequest;
use App\Http\Resources\Daily\DailyEditResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Resources\Daily\Income\AllDailyIncomeResource;
use App\Http\Requests\Daily\Report\ReportDailyRequestSearch;
use App\Http\Resources\ActivityLog\AllActionDailyActivityResource;

class DailyController extends Controller implements HasMiddleware
{
       protected $dailyService;
       protected $dailyReportService;


    public function __construct(DailyService $dailyService,DailyReportService $dailyReportService)
    {
        $this->dailyService = $dailyService;
        $this->dailyReportService = $dailyReportService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('permission:daily', only:['index']),
            new Middleware('permission:create_daily', only:['create']),
            new Middleware('permission:edit_daily', only:['edit']),
            new Middleware('permission:update_daily', only:['update']),
            new Middleware('permission:destroy_daily', only:['destroy']),
            new Middleware('permission:close_daily', only:['closeDaily']),
            new Middleware('permission:daily_report', only:['dailyReport']),
            new Middleware('tenant'),
        ];
    }

    public function index(Request $request)
    {
        $dailies= $this->dailyService->allDailies($request);
        return ApiResponse::success(new AllDailyResource($dailies));
    }
    public function create(CreateDailyRequest $createDailyRequest)
    {
        try {
            DB::beginTransaction();
            $this->dailyService->createDaily($createDailyRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (Throwable $th) {
            DB::rollBack( );
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function show(int $id)
    {
        try {
            $daily= $this->dailyService->editDaily($id);
            return ApiResponse::success(new DailyEditResource($daily));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function update(int $id,UpdateDailyRequest $updateDailyRequest)
    {
        try {
            DB::beginTransaction();
            $this->dailyService->updateDaily($id,$updateDailyRequest->validated());
            DB::commit();
            return ApiResponse::success([], __('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function destroy(int $id)
    {
        try{
            $this->dailyService->deleteDaily($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function closeDaily()
    {
        try {
            $daily=   $this->dailyService->closeDaily();
            return ApiResponse::success([
                'incoming'=>$daily->total_income??0,
                'expense'=>$daily->total_expense??0,
                'profit'=>$daily->total_profit??0,
            ], __('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'),$th->getMessage(),HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function dailyReport(ReportDailyRequestSearch $reportDailyRequestSearch)
    {
        try {
            //new DailyReportResource
            $dailyReport= $this->dailyReportService->dailyReport($reportDailyRequestSearch->validated());
            return ApiResponse::success($dailyReport);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function monthlyChartData(Request $request)
    {
        try {
            $data=$request->validate([
                'dailyId'=>'required|exists:dailies,id',
            ]);
            $monthlyChartData= $this->dailyReportService->getMonthlyChartData($data['dailyId']);
            return ApiResponse::success($monthlyChartData);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function activityLog(Request $request)
    {
        try {
            $activities= $this->dailyService->activityLog($request->query('dailyId'));
            return ApiResponse::success(new AllActionDailyActivityResource($activities));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function checkBookedDeviceOpen(Request $request)
    {
        try {
            $request->validate([
                'dailyId'=>'required|exists:dailies,id',
            ]);
            $daily= $this->dailyService->checkOpenBookedDevicesInDaily($request->dailyId);
            return ApiResponse::success($daily);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
