<?php

namespace App\Http\Controllers\API\V1\Dashboard\Device;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Services\Device\DeviceTime\DeviceTimeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Resources\Devices\DeviceTime\DeviceTimeResource;
use App\Http\Requests\Device\DeviceTime\CreateDeviceTimeRequest;
use App\Http\Requests\Device\DeviceTime\UpdateDeviceTimeRequest;
use App\Http\Resources\Devices\DeviceTime\AllDeviceTimeResource;
use Illuminate\Validation\ValidationException;

class DeviceTimeController extends Controller
{
    protected $deviceTimeService;
    public function __construct(DeviceTimeService $deviceTimeService)
    {
        $this->deviceTimeService = $deviceTimeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $deviceTimes=$this->deviceTimeService->allDeviceTimes($request);
        return ApiResponse::success(DeviceTimeResource::Collection($deviceTimes));
    }

    /**
     * Display the specified resource.
     */
      public function show($id)
    {
        try {
            $deviceTime=$this->deviceTimeService->editDeviceTime($id);
            return ApiResponse::success(new DeviceTimeResource($deviceTime));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function store(CreateDeviceTimeRequest $createDeviceTimeRequest)
    {
        try {
             $this->deviceTimeService->createDeviceTime($createDeviceTimeRequest->validated());
            return ApiResponse::success([],__('crud.created'));
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }

    public function update(UpdateDeviceTimeRequest $updateDeviceTimeRequest, $id)
    {
        try {
              $this->deviceTimeService->updateDeviceTime($id, $updateDeviceTimeRequest->validated());
            return ApiResponse::success([],__('crud.updated'));
        } catch (ValidationException $e) {
            return ApiResponse::error(__('validation.validation_error'), $e->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (QueryException  $e) {
            return ApiResponse::error(__('crud.dont_delete_device_time'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }

    public function destroy(int $id)
    {
        try {
            $this->deviceTimeService->deleteDeviceTime($id);
            return ApiResponse::success([], __('crud.deleted'));
        } catch (ValidationException $e) {
            return ApiResponse::error(__('validation.validation_error'), $e->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (QueryException  $e) {
            return ApiResponse::error(__('crud.dont_delete_device_time'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }
    public function restore(int $id)
    {
        try {
            $this->deviceTimeService->restoreDeviceTime($id);
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
            $this->deviceTimeService->forceDeleteDeviceTime($id);
            return ApiResponse::success([], __('crud.deleted'));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (QueryException  $e) {
            return ApiResponse::error(__('crud.dont_delete_device_time'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
}




