<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Services\Timer\DeviceTimerService;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Http\Resources\Timer\SessionDevice\SessionDeviceResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\Middleware;

class EndGroupTimesController extends Controller  implements HasMiddleware
{
    /**
     * Handle the incoming request.
     */
    public function __construct( protected DeviceTimerService $timerService)
    {

    }
        public static function middleware(): array
    {
        return [//products , create_products,edit_product,update_product ,destroy_product
            new Middleware('auth:api'),
            new Middleware('permission:products', only:['index']),
            new Middleware('tenant'),
        ];
    }
    public function __invoke(Request $request)
    {
            $validated=$request->validate([
                 'sessionDeviceId' => 'required|exists:session_devices,id'
            ]);
        try {
            DB::beginTransaction();
               $sessionDevice= SessionDevice::with('bookedDevices')->findOrFail($validated['sessionDeviceId']);
               if(!$sessionDevice){
                return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
               }
               foreach($sessionDevice->bookedDevices as $device){
                 $this->timerService->finish($device->id);
               }
               //, 'bookedDevices.device.media'
               //sessionDevice,deviceType,deviceTime,device
                $sessionDevice = $sessionDevice->fresh([
                    'bookedDevices.device',
                    'bookedDevices.deviceType',
                    'bookedDevices.deviceTime',
                    'bookedDevices.device.media',
                ]);
            DB::commit();
            return ApiResponse::success(new SessionDeviceResource($sessionDevice));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
