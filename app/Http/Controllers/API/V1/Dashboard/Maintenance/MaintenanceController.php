<?php

namespace App\Http\Controllers\API\V1\Dashboard\Maintenance;

use App\Http\Resources\Maintenance\MaintenanceResource;
use Throwable;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Routing\Controllers\Middleware;

use App\Services\Maintenance\MaintenanceServcie;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Maintenance\CreateMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceRequest;
use App\Http\Resources\Maintenance\AllMaintenanceResource;

class MaintenanceController extends Controller implements HasMiddleware
{
       protected $maintenanceService;


    public function __construct(MaintenanceServcie $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            // new Middleware('permission:all_expenses', only:['index']),
            // new Middleware('permission:create_expense', only:['create']),
            // new Middleware('permission:edit_expense', only:['edit']),
            // new Middleware('permission:update_expense', only:['update']),
            // new Middleware('permission:destroy_expense', only:['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $Maintenances = $this->maintenanceService->allMaintenances($request);
        return ApiResponse::success(MaintenanceResource::collection($Maintenances));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateMaintenanceRequest $createMaintenanceRequest)
    {
        try {
            DB::beginTransaction();
               $this->maintenanceService->createMaintenance($createMaintenanceRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (Throwable $th) {
            DB::rollBack( );
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
         $Maintenance =  $this->maintenanceService->editMaintenance($id);
            return ApiResponse::success(new MaintenanceResource($Maintenance));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMaintenanceRequest $updateMaintenanceRequest, int $id)
    {
        try {
            DB::beginTransaction();
            $this->maintenanceService->updateMaintenance($id,$updateMaintenanceRequest->validated());
            DB::commit();
            return ApiResponse::success([], __('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $this->maintenanceService->deleteMaintenance($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

}
