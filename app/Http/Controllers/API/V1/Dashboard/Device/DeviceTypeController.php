<?php

namespace App\Http\Controllers\API\V1\Dashboard\Device;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Device\DeviceType\CreateDeviceTypeRequest;
use App\Http\Requests\Device\DeviceType\UpdateDeviceTypeRequest;
use App\Http\Resources\Devices\DeviceType\AllDeviceTypeResource;
use App\Http\Resources\Devices\DeviceType\DeviceTypeEditResource;
use App\Services\Device\DeviceType\DeviceTypeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceTypeController extends Controller
{
    public function __construct(protected DeviceTypeService $deviceTypeService)
    {
    }

    public function index(Request $request)
    {
        $deviceTypes = $this->deviceTypeService->allDeviceTypes($request);

        return ApiResponse::success(new AllDeviceTypeResource($deviceTypes));
    }

    public function show($id)
    {
        try {
            $deviceTime = $this->deviceTypeService->editDeviceType($id);

            return ApiResponse::success(new DeviceTypeEditResource($deviceTime));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function store(CreateDeviceTypeRequest $createDeviceTypeRequest)
    {
        try {
            $this->deviceTypeService->createDeviceType($createDeviceTypeRequest->validated());

            return ApiResponse::success([], __('crud.created'));
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function update(UpdateDeviceTypeRequest $updateDeviceTypeRequest, $id)
    {
        try {
            $this->deviceTypeService->updateDeviceType($id, $updateDeviceTypeRequest->validated());

            return ApiResponse::success([], __('crud.updated'));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
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
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (QueryException $e) {
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
        } catch (QueryException $e) {
            return ApiResponse::error(__('crud.dont_delete_device_type'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
}
