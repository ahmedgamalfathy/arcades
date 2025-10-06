<?php

namespace App\Http\Controllers\API\V1\Dashboard\Device;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Enums\ActionStatusEnum;
use App\Enums\Device\DeviceStatusEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rules\Enum;
use App\Services\Device\DeviceService;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\Device\CreateDeviceRequest;
use App\Http\Requests\Device\UpdateDeviceRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Resources\Devices\Device\DeviceResource;
use App\Services\Device\DeviceTime\DeviceTimeService;
use App\Http\Resources\Devices\Device\AllDeviceResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeviceController extends Controller  implements HasMiddleware
{
    protected $deviceTimeService;
    protected $deviceService;
    public function __construct(DeviceTimeService $deviceTimeService ,DeviceService $deviceService)
    {
        $this->deviceTimeService = $deviceTimeService;
        $this->deviceService = $deviceService;
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
    public function index(Request $request)
    {
        $devices=$this->deviceService->allDevices($request);
        return ApiResponse::success(new AllDeviceResource ($devices));
    }

    /**
     * Display the specified resource.
     */
      public function show($id)
    {
        try {
            $device=$this->deviceService->editDevice($id);
            return ApiResponse::success(new  DeviceResource($device));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CreateDeviceRequest $createDeviceRequest)
    {
        try {
            DB::beginTransaction();
            $data =$createDeviceRequest->validated();
            $this->deviceService->createDevice($data);
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function update(UpdateDeviceRequest $updateDeviceRequest, $id)
    {
        try {
            DB::beginTransaction();
            $data =$updateDeviceRequest->validated();
            $this->deviceService->updateDevice($id,$data );
            DB::commit();
            return ApiResponse::success([],__('crud.updated'));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function destroy(int $id)
    {
        try {
            $this->deviceService->deleteDevice($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
        public function changeStatus($id , Request $request)
    {
        try {
           $data= $request->validate([
            "status"=>['required','string',new Enum(DeviceStatusEnum::class) ]
           ]);
            $this->deviceService->changeDeviceStatus($id,$data );
            return ApiResponse::success([],__('crud.updated'));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

}
