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
use App\Services\Timer\DeviceTimerCreationService;
use App\Http\Requests\Timer\Group\CreateGroupRequest;
use App\Http\Requests\Timer\FinishBookedDeviceRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Timer\Individual\CreateIndividualRequest;
use App\Http\Resources\Timer\BookedDevice\BookedDeviceResource;
use App\Http\Resources\Timer\BookedDevice\BookedDeviceEditResource;
use Illuminate\Validation\ValidationException;

class DeviceTimerController extends Controller
{
    public function __construct(private DeviceTimerCreationService $timerCreationService)
    {
    }

    private function timerService(): DeviceTimerService
    {
        return app(DeviceTimerService::class);
    }

    private function bookedDeviceService(): BookedDeviceService
    {
        return app(BookedDeviceService::class);
    }

    public function individualTime(CreateIndividualRequest $createIndividualRequest)
    {
        try {
            $this->timerCreationService->createIndividual($createIndividualRequest->validated());

            return ApiResponse::success([], __('crud.created'));
        } catch (ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'), $th->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }
    public function groupTime(CreateGroupRequest $createGroupRequest){
        try {
            $this->timerCreationService->createGroup($createGroupRequest->validated());

            return ApiResponse::success([], __('crud.created'));
        } catch (ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'), $th->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function pause($id)
    {
        try {
                $device = $this->bookedDeviceService()->editBookedDevice($id);
                $this->timerService()->pause($device->id);
            return ApiResponse::success([],__('crud.created'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }

    public function resume($id)
    {
        try {
              $this->timerService()->resume($id);
            return ApiResponse::success([],__('crud.created'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }

    public function finish(FinishBookedDeviceRequest $request, $id)
    {
        try {
                $data = $request->validated();
                $finished = $this->timerService()->finish($id,$data);
                // $data=[
                //     'message' => 'Device finished',
                //     'total_seconds' => $finished->total_used_seconds,
                //     'price' => $finished->calculatePrice(),
                // ];
         return ApiResponse::success(new  BookedDeviceResource($finished));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }

    public function changeTime($id, Request $request)
    {
        try {
                $request->validate(['deviceTimeId' => 'required|exists:device_times,id']);
                $device = $this->bookedDeviceService()->editBookedDevice($id);
                $newDevice = $this->timerService()->changeDeviceTime($device->id, $request->deviceTimeId);
            return ApiResponse::success([
                "newBookedDeviceId"=>$newDevice->id ??0
            ],__('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
    //show device timer
    public function show($id){
        try {
            $device = $this->bookedDeviceService()->editBookedDevice($id);
            return ApiResponse::success(new BookedDeviceEditResource($device));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
    public function getActitvityLogToDevice($bookedDeviceId){
        try {
            // Get grouped activities from service (already grouped)
            $groupedActivities = $this->bookedDeviceService()->getActivityLogToDevice($bookedDeviceId);

            // Get user names
            $userIds = $groupedActivities->pluck('causer_id')->unique()->filter();
            $users = DB::connection('mysql')->table('users')
                ->whereIn('id', $userIds)
                ->pluck('name', 'id');

            $allActivities = $groupedActivities->map(function ($activity) use ($users) {
                $activity->properties = is_string($activity->properties)
                    ? json_decode($activity->properties, true)
                    : $activity->properties;
                $activity->causerName = $users[$activity->causer_id] ?? null;
                return $activity;
            });

            return ApiResponse::success(
                \App\Http\Resources\ActivityLog\Test\AllDailyActivityResource::collection($allActivities)
            );
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    //delete device timer
    public function destroy(int $id){
        try {
            $this->bookedDeviceService()->deleteBookedDevice($id);
            return ApiResponse::success([],__('crud.deleted'));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function restore($id)
    {
        try {
            $this->bookedDeviceService()->restoreBookedDevice($id);
            return ApiResponse::success([], __('crud.restored'));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

    public function forceDelete($id)
    {
        try {
            $this->bookedDeviceService()->forceDeleteBookedDevice($id);
            return ApiResponse::success([], __('crud.deleted'));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
    //update device end date time
    public function updateEndDateTime($id, Request $request){
        try {
                $request->validate([
                    'endDateTime' => [
                        'nullable',
                        'date_format:Y-m-d H:i:s',
                        function ($attribute, $value, $fail) use ($id) {
                            $bookedDevice = $this->bookedDeviceService()->editBookedDevice($id);
                            $start = Carbon::parse($bookedDevice->start_date_time);
                            $end = Carbon::parse($value);
                            if ($end->lessThanOrEqualTo($start)) {
                                $fail('The End Time must be after the Start Time.');
                            }
                        },
                    ],
                ]);
                $device = $this->bookedDeviceService()->editBookedDevice($id);
                $this->bookedDeviceService()->updateEndDateTime($device->id, $request->all());
            return ApiResponse::success([],__('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
    //transfer device to group
    public function transferDeviceToGroup($id, Request $request){
        try {
                $request->validate([
                    'name' => 'required_without:sessionDeviceId|nullable|string',
                    'sessionDeviceId' => 'required_without:name|nullable|exists:session_devices,id',
                ]);
                $device = $this->bookedDeviceService()->editBookedDevice($id);
                $this->bookedDeviceService()->transferDeviceToGroup($device->id, $request->all());
            return ApiResponse::success([],__('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
    public function transferBookedDeviceToSessionDevice($id, Request $request){
        try {
            $data = $request->validate([
                'dailyId' => 'required|exists:dailies,id',
            ]);
            $this->bookedDeviceService()->transferBookedDeviceToSessionDevice($id,$data['dailyId']);
            return ApiResponse::success([],__('crud.updated'));
        }catch (ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'), $th->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }

}




