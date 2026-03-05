<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer;

use App\Enums\BookedDevice\BookedDeviceEnum;
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
            new Middleware('permission:destroy_product', only:['destroy', 'restore', 'forceDelete']),
            new Middleware('tenant'),
        ];
    }
    public function individualTime(CreateIndividualRequest $createIndividualRequest)
    {
        try {

         DB::beginTransaction();
         $data = $createIndividualRequest->validated();

        // Create SessionDevice without automatic logging
        $sessionDevice = \App\Models\Timer\SessionDevice\SessionDevice::withoutEvents(function () use ($data) {
            return \App\Models\Timer\SessionDevice\SessionDevice::create([
                'name' => 'individual',
                'type' => SessionDeviceEnum::INDIVIDUAL->value,
                'daily_id' => $data['dailyId']
            ]);
        });

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

        // Set status based on start/end time
        if (!empty($data['startDateTime']) && !empty($data['endDateTime'])) {
            $data['status'] = BookedDeviceEnum::ACTIVE->value;
            $data['totalUsedSeconds'] = $start->diffInSeconds($end);
        } else {
            $data['status'] = BookedDeviceEnum::ACTIVE->value;
        }

        // Create device without automatic logging
        $device = $this->bookedDeviceService->createBookedDeviceWithoutLog($data);

        // Manual activity log for SessionDevice with BookedDevice as child
        activity()
            ->useLog('SessionDevice')
            ->event('created')
            ->performedOn($sessionDevice)
            ->withProperties([
                'attributes' => [
                    'id' => $sessionDevice->id,
                    'name' => $sessionDevice->name,
                    'type' => $sessionDevice->type,
                ],
                'old' => [
                    'name' => '',
                    'type' => '',
                ],
                'children' => [[
                    'id' => $device->id,
                    'event' => 'created',
                    'log_name' => 'BookedDevice',
                    'device_id' => $device->device_id,
                    'device_type_id' => $device->device_type_id,
                    'device_time_id' => $device->device_time_id,
                    'status' => $device->status,
                ]],
            ])
            ->tap(function ($activity) use ($sessionDevice) {
                $activity->daily_id = $sessionDevice->daily_id;
            })
            ->log('SessionDevice - Individual time created');

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
             // Create new group session without automatic logging
             $sessionDevice = \App\Models\Timer\SessionDevice\SessionDevice::withoutEvents(function () use ($data) {
                return \App\Models\Timer\SessionDevice\SessionDevice::create([
                    'name' => $data['name'],
                    'type' => SessionDeviceEnum::GROUP->value,
                    'daily_id' => $data['dailyId']
                ]);
            });
            $data['sessionDeviceId']=$sessionDevice->id;
            $isNewSession = true;
        }elseIf($data['sessionDeviceId']){
            $sessionDevice= $this->sessionDeviceService->editSessionDevice($data['sessionDeviceId']);
            $data['sessionDeviceId']=$sessionDevice->id;
            $isNewSession = false;
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

        // Set status based on start/end time
        if (!empty($data['startDateTime']) && !empty($data['endDateTime'])) {
            $data['status'] = BookedDeviceEnum::ACTIVE->value;
            $data['totalUsedSeconds'] = $start->diffInSeconds($end);
        } else {
            $data['status'] = BookedDeviceEnum::ACTIVE->value;
        }

        // Create device without automatic logging
        $device = $this->bookedDeviceService->createBookedDeviceWithoutLog($data);

        // Manual activity log for SessionDevice with BookedDevice as child
        if ($isNewSession) {
            // New session - created event
            activity()
                ->useLog('SessionDevice')
                ->event('created')
                ->performedOn($sessionDevice)
                ->withProperties([
                    'attributes' => [
                        'id' => $sessionDevice->id,
                        'name' => $sessionDevice->name,
                        'type' => $sessionDevice->type,
                    ],
                    'old' => [
                        'name' => '',
                        'type' => '',
                    ],
                    'children' => [[
                        'id' => $device->id,
                        'event' => 'created',
                        'log_name' => 'BookedDevice',
                        'device_id' => $device->device_id,
                        'device_type_id' => $device->device_type_id,
                        'device_time_id' => $device->device_time_id,
                        'status' => $device->status,
                    ]],
                ])
                ->tap(function ($activity) use ($sessionDevice) {
                    $activity->daily_id = $sessionDevice->daily_id;
                })
                ->log('SessionDevice - Group time created');
        } else {
            // Existing session - updated event
            activity()
                ->useLog('SessionDevice')
                ->event('updated')
                ->performedOn($sessionDevice)
                ->withProperties([
                    'attributes' => [
                        'id' => $sessionDevice->id,
                        'name' => $sessionDevice->name,
                        'type' => $sessionDevice->type,
                    ],
                    'old' => [
                        'id' => $sessionDevice->id,
                        'name' => $sessionDevice->name,
                        'type' => $sessionDevice->type,
                    ],
                    'children' => [[
                        'id' => $device->id,
                        'event' => 'created',
                        'log_name' => 'BookedDevice',
                        'device_id' => $device->device_id,
                        'device_type_id' => $device->device_type_id,
                        'device_time_id' => $device->device_time_id,
                        'status' => $device->status,
                    ]],
                ])
                ->tap(function ($activity) use ($sessionDevice) {
                    $activity->daily_id = $sessionDevice->daily_id;
                })
                ->log('SessionDevice - New device added to group');
        }

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
    public function getActitvityLogToDevice($bookedDeviceId){
        try {
            // Get grouped activities from service (already grouped)
            $groupedActivities = $this->bookedDeviceService->getActivityLogToDevice($bookedDeviceId);

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
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    private function groupParentChildActivities($activities)
    {
        $grouped = collect();
        $childrenMap = [
            'order' => [],
            'sessiondevice' => []
        ];

        // First pass: identify ALL children for each parent
        foreach ($activities as $activity) {
            $modelName = strtolower($activity->log_name);

            if ($modelName === 'orderitem') {
                $orderId = $activity->properties['attributes']['order_id'] ??
                           $activity->properties['old']['order_id'] ?? null;
                if ($orderId) {
                    if (!isset($childrenMap['order'][$orderId])) {
                        $childrenMap['order'][$orderId] = [];
                    }
                    $childrenMap['order'][$orderId][] = $activity;
                }
            } elseif ($modelName === 'bookeddevice') {
                // Try to get session_device_id from properties first
                $sessionId = $activity->properties['attributes']['session_device_id'] ??
                             $activity->properties['old']['session_device_id'] ?? null;

                // If not in properties, get it from the actual BookedDevice record
                if (!$sessionId && $activity->subject_id) {
                    $bookedDevice = \App\Models\Timer\BookedDevice\BookedDevice::find($activity->subject_id);
                    $sessionId = $bookedDevice?->session_device_id;
                }

                if ($sessionId) {
                    if (!isset($childrenMap['sessiondevice'][$sessionId])) {
                        $childrenMap['sessiondevice'][$sessionId] = [];
                    }
                    $childrenMap['sessiondevice'][$sessionId][] = $activity;
                }
            }
        }

        // Second pass: group parents with children
        $processedChildren = [];

        foreach ($activities as $activity) {
            $modelName = strtolower($activity->log_name);
            $activityId = $activity->id;

            // Skip if already processed as a child
            if (in_array($activityId, $processedChildren)) {
                continue;
            }

            if ($modelName === 'order') {
                $orderId = $activity->subject_id;

                // Check if this Order uses LogBatch (has children in properties)
                $propertiesChildren = $activity->properties['children'] ?? [];

                if (!empty($propertiesChildren)) {
                    // LogBatch system: children are already in properties
                    if ($activity->event === 'updated') {
                        $oldItems = $activity->properties['old']['items'] ?? [];
                        $oldItemsMap = collect($oldItems)->keyBy('id');
                        $newItemsMap = collect($propertiesChildren)->keyBy('id');

                        $children = collect($propertiesChildren)->map(function($childData) use ($oldItemsMap) {
                            $itemId = $childData['id'] ?? null;
                            $oldItem = $oldItemsMap->get($itemId);

                            if (!$oldItem) {
                                return (object)[
                                    'log_name' => 'OrderItem',
                                    'event' => 'created',
                                    'properties' => ['attributes' => $childData]
                                ];
                            }

                            $hasChanges = false;
                            $importantFields = ['product_id', 'qty', 'price'];

                            foreach ($importantFields as $field) {
                                if (isset($oldItem[$field]) && isset($childData[$field])) {
                                    if ($oldItem[$field] != $childData[$field]) {
                                        $hasChanges = true;
                                        break;
                                    }
                                }
                            }

                            if ($hasChanges) {
                                return (object)[
                                    'log_name' => 'OrderItem',
                                    'event' => 'updated',
                                    'properties' => [
                                        'attributes' => $childData,
                                        'old' => $oldItem
                                    ]
                                ];
                            }

                            return null;
                        })->filter();

                        $oldIds = $oldItemsMap->keys();
                        $newIds = $newItemsMap->keys();
                        $deletedIds = $oldIds->diff($newIds);

                        foreach ($deletedIds as $deletedId) {
                            $deletedItem = $oldItemsMap->get($deletedId);
                            $children->push((object)[
                                'log_name' => 'OrderItem',
                                'event' => 'deleted',
                                'properties' => ['old' => $deletedItem]
                            ]);
                        }

                        $activity->children = $children->all();
                    } else {
                        $activity->children = collect($propertiesChildren)->map(function($childData) use ($activity) {
                            $properties = [];

                            if ($activity->event === 'deleted') {
                                $properties = ['old' => $childData];
                            } else {
                                $properties = ['attributes' => $childData];
                            }

                            return (object)[
                                'log_name' => 'OrderItem',
                                'event' => $activity->event,
                                'properties' => $properties
                            ];
                        })->all();
                    }
                } else {
                    // Legacy system: look for separate OrderItem activities
                    $allChildren = $childrenMap['order'][$orderId] ?? [];
                    $activity->children = collect($allChildren)->filter(function($child) use ($activity) {
                        $sameEvent = strtolower($child->event) === strtolower($activity->event);
                        if (!$sameEvent) {
                            return false;
                        }

                        $parentTime = \Carbon\Carbon::parse($activity->created_at);
                        $childTime = \Carbon\Carbon::parse($child->created_at);
                        $timeDiff = abs($parentTime->diffInSeconds($childTime));

                        return $timeDiff <= 10;
                    })->values()->all();

                    foreach ($activity->children as $child) {
                        $processedChildren[] = $child->id;
                    }
                }

                $grouped->push($activity);

            } elseif ($modelName === 'sessiondevice') {
                $sessionId = $activity->subject_id;

                // Check if this SessionDevice uses children in properties
                $propertiesChildren = $activity->properties['children'] ?? [];

                if (!empty($propertiesChildren)) {
                    // Children are in properties - convert to objects for resource processing
                    $activity->children = collect($propertiesChildren)->map(function($childData) {
                        $event = $childData['event'] ?? 'updated';

                        // For 'created' event, only use attributes (no old values)
                        if ($event === 'created') {
                            return (object)[
                                'log_name' => $childData['log_name'] ?? 'BookedDevice',
                                'event' => $event,
                                'subject_id' => $childData['id'] ?? null,
                                'properties' => [
                                    'attributes' => [
                                        'device_id' => $childData['device_id'] ?? null,
                                        'device_type_id' => $childData['device_type_id'] ?? null,
                                        'device_time_id' => $childData['device_time_id'] ?? null,
                                        'status' => $childData['status'] ?? null,
                                    ]
                                ]
                            ];
                        }

                        // For 'deleted' event, use old values
                        if ($event === 'deleted') {
                            return (object)[
                                'log_name' => $childData['log_name'] ?? 'BookedDevice',
                                'event' => $event,
                                'subject_id' => $childData['id'] ?? null,
                                'properties' => [
                                    'old' => [
                                        'device_id' => $childData['device_id'] ?? null,
                                        'device_type_id' => $childData['device_type_id'] ?? null,
                                        'device_time_id' => $childData['device_time_id'] ?? null,
                                        'status' => $childData['status'] ?? null,
                                    ]
                                ]
                            ];
                        }

                        // For 'updated' event, include both old and new values
                        return (object)[
                            'log_name' => $childData['log_name'] ?? 'BookedDevice',
                            'event' => $event,
                            'subject_id' => $childData['id'] ?? null,
                            'properties' => [
                                'attributes' => [
                                    'device_id' => $childData['device_id'] ?? null,
                                    'device_type_id' => $childData['device_type_id'] ?? null,
                                    'device_time_id' => $childData['device_time_id'] ?? null,
                                    'status' => $childData['status'] ?? null,
                                    'end_date_time' => $childData['end_date_time'] ?? null,
                                ],
                                'old' => [
                                    'device_id' => $childData['device_id'] ?? null,
                                    'device_type_id' => $childData['device_type_id'] ?? null,
                                    'device_time_id' => $childData['old_device_time_id'] ?? $childData['device_time_id'] ?? null,
                                    'status' => $childData['old_status'] ?? null,
                                    'old_end_date_time' => $childData['old_end_date_time'] ?? null,
                                ]
                            ]
                        ];
                    })->all();
                } else {
                    // Legacy system: look for separate BookedDevice activities
                    $allChildren = $childrenMap['sessiondevice'][$sessionId] ?? [];
                    $activity->children = collect($allChildren)->values()->all();

                    foreach ($activity->children as $child) {
                        $processedChildren[] = $child->id;
                    }
                }

                $grouped->push($activity);

            } elseif ($modelName === 'orderitem') {
                // Skip standalone OrderItems (they should be children of Orders)
                continue;
            } elseif ($modelName === 'bookeddevice') {
                // Skip standalone BookedDevices (they should be children of SessionDevices)
                continue;
            } else {
                // Other models (BookedDevicePause, etc.)
                $activity->children = [];
                $grouped->push($activity);
            }
        }

        return $grouped;
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

    public function restore($id)
    {
        try {
            $this->bookedDeviceService->restoreBookedDevice($id);
            return ApiResponse::success([], __('crud.restored'));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'), $th->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    public function forceDelete($id)
    {
        try {
            $this->bookedDeviceService->forceDeleteBookedDevice($id);
            return ApiResponse::success([], __('crud.deleted'));
        } catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'), $th->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    //update device end date time
    public function updateEndDateTime($id, Request $request){
        try {
            DB::beginTransaction();
                $request->validate([
                    'endDateTime' => [
                        'nullable',
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





