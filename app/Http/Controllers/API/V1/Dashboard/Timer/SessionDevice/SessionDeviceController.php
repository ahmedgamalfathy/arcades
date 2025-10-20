<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer\SessionDevice;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Services\Timer\SessionDeviceService;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Resources\Timer\SessionDevice\SessionDeviceResource;
use App\Http\Resources\Timer\SessionDevice\AllSessionResource;

class SessionDeviceController extends Controller  implements HasMiddleware
{
    public function __construct(
    protected SessionDeviceService $sessionDeviceService
    )
    {
    }
            public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('permission:products', only:['index']),
            new Middleware('permission:edit_product', only:['edit']),
            new Middleware('permission:destroy_product', only:['destroy']),
            new Middleware('tenant'),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $sessionDevices = $this->sessionDeviceService->getSessionDevices($request);
            return ApiResponse::success(new AllSessionResource($sessionDevices));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'),$e->getMessage(), HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $e ) {
            return ApiResponse::error(__('crud.server_error'),$e->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $sessionDevice = $this->sessionDeviceService->editSessionDevice($id);
            return ApiResponse::success(new SessionDeviceResource($sessionDevice));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'),$e->getMessage(), HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $e ) {
            return ApiResponse::error(__('crud.server_error'),$e->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->sessionDeviceService->deleteSessionDevice($id);
            return ApiResponse::success([],__('crud.deleted'));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'),$e->getMessage(), HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $e ) {
            return ApiResponse::error(__('crud.server_error'),$e->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
