<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer;

use Carbon\Carbon;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Services\Timer\DeviceTimerService;
use App\Services\Timer\BookedDeviceService;
use App\Services\Timer\SessionDeviceService;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Http\Requests\Timer\Individual\CreateIndividualRequest;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeviceTimerController extends Controller  implements HasMiddleware
{
    public function __construct(
        protected SessionDeviceService $sessionDeviceService,
        protected DeviceTimerService $timerService,
        protected BookedDeviceService $bookedDeviceService
        )
    {
    }
        public static function middleware(): array
    {
        return [//products , create_products,edit_product,update_product ,destroy_product
            new Middleware('auth:api'),
            new Middleware('permission:products', only:['index']),
            new Middleware('permission:create_products', only:['create']),
            new Middleware('permission:edit_product', only:['edit']),
            new Middleware('permission:update_product', only:['update']),
            new Middleware('permission:destroy_product', only:['destroy']),
            new Middleware('tenant'),
        ];
    }

//Individual
    public function startIndividual(CreateIndividualRequest $createIndividualRequest)
    {
        try {
         DB::beginTransaction();
        $sessionDevice= $this->sessionDeviceService->createSessionDevice([
            'name'=>'individual',
            'type'=>SessionDeviceEnum::INDIVIDUAL->value
        ]);
        $data = $createIndividualRequest->validated();

        $start = Carbon::parse($data['start_date_time']);
        $end   = $data['end_date_time'] ? Carbon::parse($data['end_date_time']) : null;

        if ($end && $end->lessThanOrEqualTo($start)) {
        return ApiResponse::error("The end time must be after the start time.");
        }
        $data['session_device_id']=$sessionDevice->id;

        $device = $this->timerService->startOrSetTime($data);
        DB::commit();
        return ApiResponse::success($device);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }


    public function pause($id)
    {
        try {
            DB::beginTransaction();
                $device = $this->bookedDeviceService->editBookedDevice($id);
                $this->timerService->pause($device->id);
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function resume($id)
    {
        try {
            DB::beginTransaction();
              $this->timerService->resume($id);
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function finish($id)
    {
        try {
            DB::beginTransaction();
                $finished = $this->timerService->finish($id);
                $data=[
                    'message' => 'Device finished',
                    'total_seconds' => $finished->total_used_seconds,
                    'price' => $finished->calculatePrice(),
                ];
            DB::commit();
         return ApiResponse::success($data);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function changeTime($id, Request $request)
    {
        try {
            DB::beginTransaction();
                $request->validate(['device_time_id' => 'required|exists:device_times,id']);
                $device = $this->bookedDeviceService->editBookedDevice($id);
                $newDevice = $this->timerService->changeDeviceTime($device->id, $request->device_time_id);
            DB::commit();
            return ApiResponse::success($newDevice,__('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}





