<?php

namespace App\Http\Controllers\API\V1\Dashboard\Maintenance;

use App\Http\Resources\Maintenance\MaintenanceResource;
use Throwable;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Enums\ResponseCode\HttpStatusCode;

use App\Services\Maintenance\MaintenanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Maintenance\CreateMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceRequest;
use App\Http\Resources\Maintenance\AllMaintenanceResource;

class MaintenanceController extends Controller
{
       protected $maintenanceService;


    public function __construct(MaintenanceService $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
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
               $this->maintenanceService->createMaintenance($createMaintenanceRequest->validated());
            return ApiResponse::success([],__('crud.created'));
        } catch (Throwable $th) {
            return ApiResponse::exception($th);
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
            return ApiResponse::exception($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMaintenanceRequest $updateMaintenanceRequest, int $id)
    {
        try {
            $this->maintenanceService->updateMaintenance($id,$updateMaintenanceRequest->validated());
            return ApiResponse::success([], __('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::exception($th);
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


