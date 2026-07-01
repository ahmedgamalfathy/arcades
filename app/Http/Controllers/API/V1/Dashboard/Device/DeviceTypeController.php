<?php

namespace App\Http\Controllers\API\V1\Dashboard\Device;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Enums\ActionStatusEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Validation\ValidationException;
use App\Services\Device\DeviceTime\DeviceTimeService;
use App\Services\Device\DeviceType\DeviceTypeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Device\DeviceType\CreateDeviceTypeRequest;
use App\Http\Requests\Device\DeviceType\UpdateDeviceTypeRequest;
use App\Http\Resources\Devices\DeviceType\AllDeviceTypeResource;
use App\Http\Resources\Devices\DeviceType\DeviceTypeEditResource;

class DeviceTypeController extends Controller
{
    protected $deviceTypeService;
    protected $deviceTimeService;
    public function __construct(DeviceTypeService $deviceTypeService ,DeviceTimeService $deviceTimeService)
    {
        $this->deviceTypeService = $deviceTypeService;
        $this->deviceTimeService = $deviceTimeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $deviceTypes=$this->deviceTypeService->allDeviceTypes($request);
        return ApiResponse::success(new AllDeviceTypeResource ($deviceTypes));
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $deviceTime=$this->deviceTypeService->editDeviceType($id);
            return ApiResponse::success(new DeviceTypeEditResource($deviceTime));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function store(CreateDeviceTypeRequest $createDeviceTypeRequest)
    {
        try {
            DB::beginTransaction();
            $data =$createDeviceTypeRequest->validated();
             $deviceType=$this->deviceTypeService->createDeviceType($data);
            if (!empty($data['times'])) {
                foreach ($data['times'] as $time) {
                        $time['deviceTypeId']  = $deviceType->id;
                        $this->deviceTimeService->createDeviceTime($time);
                }
            }
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::exception($th);
        }

    }

    public function update(UpdateDeviceTypeRequest $updateDeviceTypeRequest, $id)
    {
        try {
            DB::beginTransaction();
            $data =$updateDeviceTypeRequest->validated();
            $deviceType=  $this->deviceTypeService->updateDeviceType($id,$data );
            if (!empty($data['times'])) {
                foreach ($data['times'] as $time) {
                    $time['deviceTypeId']=$deviceType->id;
                    $status = isset($time['actionStatus']) ? (int)$time['actionStatus'] : ActionStatusEnum::DEFAULT->value;
                        switch ($status) {
                            case ActionStatusEnum::CREATE->value:
                            $this->deviceTimeService->createDeviceTime($time);
                            break;

                            case ActionStatusEnum::UPDATE->value:
                            $this->deviceTimeService->updateDeviceTime($time['timeTypeId'], $time);
                             break;

                            case ActionStatusEnum::DELETE->value:
                            $this->deviceTimeService->deleteDeviceTime($time['timeTypeId']);
                            break;

                            case ActionStatusEnum::DEFAULT->value:
                            default:
                                // طھط¬ط§ظ‡ظ„ ط§ظ„ط¹ظ†طµط±
                            break;
                        }
                }
            }
            DB::commit();
            return ApiResponse::success([],__('crud.updated'));
        }catch(ModelNotFoundException $e){
            DB::rollBack();
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::exception($th);
        }

    }

    public function destroy(int $id)
    {
        try {
            $this->deviceTypeService->deleteDeviceType($id);
            return ApiResponse::success([], __('crud.deleted'));
        } catch (ValidationException $e) {
            return ApiResponse::error(__('validation.validation_error'), $e->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (QueryException  $e) {
            return ApiResponse::error(__('crud.dont_delete_device_type'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }
    public function restore(int $id)
    {
        try {
            $this->deviceTypeService->restoreDeviceType($id);
            return ApiResponse::success([], __('crud.updated'));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
    public function forceDelete(int $id)
    {
        try {
            $this->deviceTypeService->forceDeleteDeviceType($id);
            return ApiResponse::success([], __('crud.deleted'));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (QueryException  $e) {
            return ApiResponse::error(__('crud.dont_delete_device_type'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
}




