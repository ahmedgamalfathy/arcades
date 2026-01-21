<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Services\Timer\DeviceTimerService;
use App\Services\Timer\BookedDeviceService;
use App\Services\Timer\SessionDeviceService;
use App\Http\Resources\Device\DeviceResource;
use App\Enums\SessionDevice\SessionDeviceEnum;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Requests\Timer\Group\CreateGroupRequest;
use App\Notifications\BookedDeviceStatusNotification;
use App\Http\Requests\Timer\FinishBookedDeviceRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Timer\Individual\CreateIndividualRequest;
use App\Http\Resources\Timer\BookedDevice\BookedDevcieResource;
use App\Http\Resources\Timer\BookedDevice\BookedDeviceEditResource;
use App\Http\Resources\ActivityLog\DeviceActivity\AllDeviceActivityResource;
use Illuminate\Validation\ValidationData;
use League\Config\Exception\ValidationException;

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
    public function individualTime(CreateIndividualRequest $createIndividualRequest)
    {
        try {

         DB::beginTransaction();
         $data = $createIndividualRequest->validated();
        $sessionDevice= $this->sessionDeviceService->createSessionDevice([
            'name'=>'individual',
            'type'=>SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id'=>$data['dailyId']
        ]);

        $start = Carbon::parse($data['startDateTime']);
        $end = $data['endDateTime']
            ? Carbon::parse($data['endDateTime'])
            : null;

        if ($end && $end->lessThanOrEqualTo($start)) {
            return ApiResponse::error("The end time must be after the start time.");
        }

        $data['startDateTime'] = $start;
        $data['endDateTime'] = $end;
        $data['sessionDeviceId'] = $sessionDevice->id;
               $this->timerService->startOrSetTime($data);
        DB::commit();
        return ApiResponse::success([],__('crud.created'));
        } catch (\Illuminate\Validation\ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'),$th->getMessage(),HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
    public function groupTime(CreateGroupRequest $createGroupRequest){
        $data= $createGroupRequest->validated();
        try {
         DB::beginTransaction();
        if( $data['name'] && $data['sessionDeviceId']){
            throw new exception("name and sessionDeviceId are required");
        }elseIf($data['name']){
             $sessionDevice= $this->sessionDeviceService->createSessionDevice([
                'name'=>$data['name'],
                'type'=>SessionDeviceEnum::GROUP->value,
                'daily_id'=>$data['dailyId']
            ]);
            $data['sessionDeviceId']=$sessionDevice->id;
        }elseIf($data['sessionDeviceId']){
            $sessionDevice= $this->sessionDeviceService->editSessionDevice($data['sessionDeviceId']);
            $data['sessionDeviceId']=$sessionDevice->id;
        }
        $start = Carbon::parse($data['startDateTime']);
        $end = $data['endDateTime']
            ? Carbon::parse($data['endDateTime'])
            : null;

        if ($end && $end->lessThanOrEqualTo($start)) {
        return ApiResponse::error("The end time must be after the start time.");
        }
        $data['startDateTime'] = $start;
        $data['endDateTime'] = $end;
        $device = $this->timerService->startOrSetTime($data);
        DB::commit();
        return ApiResponse::success([],__('crud.created'));
        } catch (\Illuminate\Validation\ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'),$th->getMessage(),HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (\Throwable $th) {
            DB::rollBack();
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

    public function finish(FinishBookedDeviceRequest $request, $id)
    {
        try {
            DB::beginTransaction();
                $data = $request->validated();
                $finished = $this->timerService->finish($id,$data);
                // $data=[
                //     'message' => 'Device finished',
                //     'total_seconds' => $finished->total_used_seconds,
                //     'price' => $finished->calculatePrice(),
                // ];
            DB::commit();
         return ApiResponse::success(new  BookedDevcieResource($finished));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function changeTime($id, Request $request)
    {
        try {
            DB::beginTransaction();
                $request->validate(['deviceTimeId' => 'required|exists:device_times,id']);
                $device = $this->bookedDeviceService->editBookedDevice($id);
                $newDevice = $this->timerService->changeDeviceTime($device->id, $request->deviceTimeId);
            DB::commit();
            return ApiResponse::success([
                "newBookedDeviceId"=>$newDevice->id ??0
            ],__('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    //show device timer
    public function show($id){
        try {
            $device = $this->bookedDeviceService->editBookedDevice($id);
            return ApiResponse::success(new BookedDeviceEditResource($device));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function getActitvityLogToDevice($id){
        try {
            $activityLog = $this->bookedDeviceService->getActivityLogToDevice($id);
            return ApiResponse::success([
                'orders'      => AllDeviceActivityResource::collection($activityLog['orders']),
                'order_items' => AllDeviceActivityResource::collection($activityLog['order_items']),
                'sessions'    => AllDeviceActivityResource::collection($activityLog['sessions']),
                'pauses'      => AllDeviceActivityResource::collection($activityLog['pauses']),
            ]);
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    //delete device timer
    public function destroy(int $id){
        try {
            $this->bookedDeviceService->deleteBookedDevice($id);
            return ApiResponse::success([],__('crud.deleted'));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    //update device end date time
    public function updateEndDateTime($id, Request $request){
        try {
            DB::beginTransaction();
                $request->validate([
                    'endDateTime' => [
                        'required',
                        'date_format:Y-m-d H:i:s',
                        function ($attribute, $value, $fail) use ($id) {
                            $bookedDevice = $this->bookedDeviceService->editBookedDevice($id);
                            $start = Carbon::parse($bookedDevice->start_date_time);
                            $end = Carbon::parse($value);
                            if ($end->lessThanOrEqualTo($start)) {
                                $fail('The End Time must be after the Start Time.');
                            }
                        },
                    ],
                ]);
                $device = $this->bookedDeviceService->editBookedDevice($id);
                $this->bookedDeviceService->updateEndDateTime($device->id, $request->all());
            DB::commit();
            return ApiResponse::success([],__('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    //transfer device to group
    public function transferDeviceToGroup($id, Request $request){
        try {
            DB::beginTransaction();
                $request->validate([
                    'name' => 'required_without:sessionDeviceId|nullable|string',
                    'sessionDeviceId' => 'required_without:name|nullable|exists:session_devices,id',
                ]);
                $device = $this->bookedDeviceService->editBookedDevice($id);
                $this->bookedDeviceService->transferDeviceToGroup($device->id, $request->all());
            DB::commit();
            return ApiResponse::success([],__('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),$th->getMessage(),HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function transferBookedDeviceToSessionDevice($id, Request $request){
        try {
            $data = $request->validate([
                'dailyId' => 'required|exists:dailies,id',
            ]);
            $this->bookedDeviceService->transferBookedDeviceToSessionDevice($id,$data['dailyId']);
            return ApiResponse::success([],__('crud.updated'));
        }catch (ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'),$th->getMessage(),HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

}





