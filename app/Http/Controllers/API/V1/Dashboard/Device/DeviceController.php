<?php

namespace App\Http\Controllers\API\V1\Dashboard\Device;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Enums\ActionStatusEnum;
use App\Enums\Order\OrderTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Order\OrderService;
use Illuminate\Validation\Rules\Enum;
use App\Enums\Device\DeviceStatusEnum;
use App\Services\Device\DeviceService;
use Illuminate\Database\QueryException;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\Device\CreateDeviceRequest;
use App\Http\Requests\Device\UpdateDeviceRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Requests\Order\CreateOrderDeviceRequest;
use App\Http\Resources\Devices\Device\DeviceResource;
use App\Services\Device\DeviceTime\DeviceTimeService;
use App\Http\Resources\Devices\Device\AllDeviceResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class DeviceController extends Controller  implements HasMiddleware
{
    protected $deviceTimeService;
    protected $deviceService;
    protected $orderService;
    public function __construct(DeviceTimeService $deviceTimeService ,DeviceService $deviceService,OrderService $orderService)
    {
        $this->deviceTimeService = $deviceTimeService;
        $this->deviceService = $deviceService;
        $this->orderService = $orderService;
    }
    public static function middleware(): array
    {
        return [//'changeStatus, devices ,create_devices, edit_device,update_device ,destroy_device
            new Middleware('auth:api'),
            new Middleware('permission:devices', only:['index']),
            new Middleware('permission:devices_changeStatus', only:['changeStatus']),
            new Middleware('permission:create_devices', only:['create']),
            new Middleware('permission:edit_device', only:['edit']),
            new Middleware('permission:update_device', only:['update']),
            new Middleware('permission:destroy_device', only:['destroy']),
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
        }catch(QueryException  $e){
            return ApiResponse::error(__('crud.dont_delete_device_time'),[],HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
        public function changeStatus($id , Request $request)
    {
        try {
           $data= $request->validate([
            "status"=>['required','string',new Enum(DeviceStatusEnum::class) ]
           ]);
            $device=$this->deviceService->changeDeviceStatus($id,$data );
            if($device){
            activity()->performedOn($device)->causedBy(auth('api')->user())->withProperties($device->toArray())->log('change device status');
            }
            return ApiResponse::success([],__('crud.updated'));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
    public function createOrderDevice(CreateOrderDeviceRequest $createOrderDeviceRequest){
        try {
            DB::beginTransaction();
            $data =$createOrderDeviceRequest->validated();
            $data['type']=OrderTypeEnum::INTERNAL->value;
            $this->orderService->createOrder($data);
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function getTimesByDeviceId(Request $request)
    {
        try {
            $request->validate([
                'deviceId' => 'required|integer|exists:devices,id',
            ]);
            $id = $request->input('deviceId');
            $deviceTimes = $this->deviceService->getTimesByDeviceId($id);
            $schemaTimes = $deviceTimes->map(function ($deviceTime) {
                return [
                    'value' => $deviceTime->id,
                    'lable' => $deviceTime->name,
                ];
            });
            return ApiResponse::success($schemaTimes);
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

}
