<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Timer\SessionDevice\SessionDeviceResource;
use App\Services\Timer\DeviceTimerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class EndGroupTimesEditedController extends Controller
{
    public function __construct(protected DeviceTimerService $timerService)
    {
    }

    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'sessionDeviceId' => 'required|exists:session_devices,id',
            'actualPaidAmount' => 'nullable|numeric|min:0',
        ]);

        try {
            $sessionDevice = $this->timerService->finishGroupSessionWithDistributedPayment(
                $validated['sessionDeviceId'],
                $validated['actualPaidAmount'] ?? null
            );

            return ApiResponse::success(new SessionDeviceResource($sessionDevice));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
}
