<?php

namespace App\Http\Controllers\API\V1\Dashboard\Daily;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Services\Daily\DailyService;
use App\Http\Resources\Daily\AllDailyResource;
use App\Helpers\ApiResponse;
use App\Http\Requests\Daily\CreateDailyRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Http\Requests\Daily\UpdateDailyRequest;
use App\Http\Resources\Daily\DailyEditResource;
use App\Http\Resources\Daily\Income\AllDailyIncomeResource;
use Throwable;
class DailyController extends Controller implements HasMiddleware
{
       protected $dailyService;


    public function __construct(DailyService $dailyService)
    {
        $this->dailyService = $dailyService;
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
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
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
            DB::beginTransaction();
            $this->dailyService->closeDaily();
            DB::commit();
            return ApiResponse::success([], __('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function dailyReport()
    {
        try {
            //new DailyReportResource
            $dailyReport= $this->dailyService->dailyReport();
            return ApiResponse::success($dailyReport);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

}
