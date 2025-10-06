<?php

namespace App\Http\Controllers\API\V1\Dashboard\Device;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Services\Device\DeviceTime\DeviceTimeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Resources\Devices\DeviceTime\DeviceTimeResource;
use App\Http\Requests\Device\DevcieTime\CreateDeviceTimeRequest;
use App\Http\Requests\Device\DevcieTime\UpdateDeviceTimeRequest;
use App\Http\Resources\Devices\DeviceTime\AllDeviceTimeResource;

class DeviceTimeController extends Controller implements HasMiddleware
{
    protected $deviceTimeService;
    public function __construct(DeviceTimeService $deviceTimeService)
    {
        $this->deviceTimeService = $deviceTimeService;
    }
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('tenant'),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $devcieTimes=$this->deviceTimeService->allDeviceTimes();
        return ApiResponse::success(DeviceTimeResource::Collection($devcieTimes));
    }

    /**
     * Display the specified resource.
     */
      public function show($id)
    {
        try {
            $devcieTime=$this->deviceTimeService->editDeviceTime($id);
            return ApiResponse::success(new DeviceTimeResource($devcieTime));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CreateDeviceTimeRequest $createDeviceTimeRequest)
    {
        try {
            DB::beginTransaction();
             $this->deviceTimeService->createDeviceTime($createDeviceTimeRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function update(UpdateDeviceTimeRequest $updateDeviceTimeRequest, $id)
    {
        try {
            DB::beginTransaction();
              $this->deviceTimeService->updateDeviceTime($id, $updateDeviceTimeRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.updated'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function destroy(int $id)
    {
        try {
            $this->deviceTimeService->deleteDeviceTime($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
}
