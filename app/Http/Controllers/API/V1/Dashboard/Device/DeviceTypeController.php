<?php

namespace App\Http\Controllers\API\V1\Dashboard\Device;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Services\Device\DeviceType\DeviceTypeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Device\DevcieType\CreateDeviceTypeRequest;
use App\Http\Requests\Device\DevcieType\UpdateDeviceTypeRequest;

class DeviceTypeController extends Controller  implements HasMiddleware
{
    protected $deviceTypeService;
    public function __construct(DeviceTypeService $deviceTypeService)
    {
        $this->deviceTypeService = $deviceTypeService;
    }
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            // new Middleware('permission:device_times', only:['index']),
            // new Middleware('permission:device_time', only:['store']),
            // new Middleware('permission:device_time', only:['edit']),
            // new Middleware('permission:device_time', only:['update']),
            // new Middleware('permission:device_time', only:['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $devcieTypes=$this->deviceTypeService->allDeviceTypes($request);
        return ApiResponse::success($devcieTypes);
    }

    /**
     * Display the specified resource.
     */
      public function show($id)
    {
        try {
            $devcieTime=$this->deviceTypeService->editDeviceType($id);
            return ApiResponse::success($devcieTime);
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CreateDeviceTypeRequest $createDeviceTimeRequest)
    {
        try {
            DB::beginTransaction();
             $this->deviceTypeService->createDeviceType($createDeviceTimeRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function update(UpdateDeviceTypeRequest $updateDeviceTimeRequest, $id)
    {
        try {
            DB::beginTransaction();
              $this->deviceTypeService->updateDeviceType($id, $updateDeviceTimeRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.updated'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function destroy(int $id)
    {
        try {
            $this->deviceTypeService->deleteDeviceType($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
}
