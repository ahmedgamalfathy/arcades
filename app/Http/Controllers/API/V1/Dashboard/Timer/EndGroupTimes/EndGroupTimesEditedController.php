<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Services\Timer\DeviceTimerService;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Http\Resources\Timer\SessionDevice\SessionDeviceResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Enums\BookedDevice\BookedDeviceEnum;

class EndGroupTimesEditedController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __construct( protected DeviceTimerService $timerService)
    {

    }
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'sessionDeviceId' => 'required|exists:session_devices,id',
            'actualPaidAmount' => 'nullable|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $sessionDevice = SessionDevice::with('bookedDevices')->findOrFail($validated['sessionDeviceId']);


            if (!$sessionDevice) {
                DB::rollBack();
                return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
            }

            $totalCost = 0;
            $finishedDevices = [];

            // ط§ظ„ط®ط·ظˆط© 1: ط¥ظ†ظ‡ط§ط، ط¬ظ…ظٹط¹ ط§ظ„ط£ط¬ظ‡ط²ط© ظˆط­ط³ط§ط¨ ط§ظ„طھظƒظ„ظپط© ط§ظ„ط¥ط¬ظ…ط§ظ„ظٹط©
            foreach ($sessionDevice->bookedDevices as $device) {
                if ($device->status != BookedDeviceEnum::FINISHED->value) {
                    $finished = $this->timerService->finish($device->id);
                    $finishedDevices[] = $finished;
                    $totalCost += $finished->period_cost;
                } else {
                    $finishedDevices[] = $device;
                    $totalCost += $device->period_cost;
                }
            }

            // ط§ظ„ط®ط·ظˆط© 2: ط­ط³ط§ط¨ ط§ظ„ظ…ط¨ظ„ط؛ ط§ظ„ظپط¹ظ„ظٹ ط§ظ„ظ…ط¯ظپظˆط¹
            $actualPaidTotal = $validated['actualPaidAmount'] ?? $totalCost;

            // ط§ظ„ط®ط·ظˆط© 3: طھظˆط²ظٹط¹ ط§ظ„ظ…ط¨ظ„ط؛ ط¹ظ„ظ‰ ط§ظ„ط£ط¬ظ‡ط²ط©
            if ($totalCost > 0 && count($finishedDevices) > 0) {
                $distributedTotal = 0;
                $devicesCount = count($finishedDevices);

                foreach ($finishedDevices as $index => $device) {
                    // ط­ط³ط§ط¨ ظ†ط³ط¨ط© ظƒظ„ ط¬ظ‡ط§ط² ظ…ظ† ط§ظ„طھظƒظ„ظپط© ط§ظ„ط¥ط¬ظ…ط§ظ„ظٹط©
                    $ratio = $device->period_cost / $totalCost;

                    // ظ„ظ„ط¬ظ‡ط§ط² ط§ظ„ط£ط®ظٹط±: ظ†ط¹ط·ظٹظ‡ ط§ظ„ط¨ط§ظ‚ظٹ ظ„طھط¬ظ†ط¨ ظ…ط´ط§ظƒظ„ ط§ظ„طھظ‚ط±ظٹط¨
                    if ($index === $devicesCount - 1) {
                        $devicePaidAmount = $actualPaidTotal - $distributedTotal;
                    } else {
                        $devicePaidAmount = round($actualPaidTotal * $ratio, 2);
                        $distributedTotal += $devicePaidAmount;
                    }

                    // طھط­ط¯ظٹط« ط§ظ„ظ…ط¨ظ„ط؛ ط§ظ„ظ…ط¯ظپظˆط¹ ظپظ‚ط·
                    $device->update([
                        'actual_paid_amount' => $devicePaidAmount
                    ]);
                }
            } else {
                // ظپظٹ ط­ط§ظ„ط© ط§ظ„طھظƒظ„ظپط© = 0طŒ ظ†ظˆط²ط¹ ط§ظ„ظ…ط¨ظ„ط؛ ط¨ط§ظ„طھط³ط§ظˆظٹ
                $equalAmount = count($finishedDevices) > 0
                    ? round($actualPaidTotal / count($finishedDevices), 2)
                    : 0;

                foreach ($finishedDevices as $device) {
                    $device->update([
                        'actual_paid_amount' => $equalAmount
                    ]);
                }
            }
            $groupedDevices = collect($finishedDevices)->groupBy(function ($device) {
                return $device->device_id . '-' . $device->device_type_id;
            });
            foreach ($groupedDevices as $devices) {

                // طھط±طھظٹط¨ ط­ط³ط¨ ط¢ط®ط± ط³ط¬ظ„
                $devices = $devices->sortBy('id')->values();

                // ط¥ط¬ظ…ط§ظ„ظٹ ظ…ط¨ظ„ط؛ ط§ظ„ط¬ظ‡ط§ط² (ظ…ط«ظ„ط§ظ‹ 100)
                $deviceTotalAmount = $devices->sum('actual_paid_amount');

                // ط¢ط®ط± record ظپظ‚ط·
                $lastDevice = $devices->last();

                foreach ($devices as $device) {
                    $device->update([
                        'actual_paid_amount' =>
                            $device->id === $lastDevice->id
                                ? $deviceTotalAmount
                                : 0
                    ]);
                }
            }

            $sessionDevice = $sessionDevice->fresh([
                'bookedDevices.device',
                'bookedDevices.deviceType',
                'bookedDevices.deviceTime',
                'bookedDevices.device.media',
            ]);

            DB::commit();

            return ApiResponse::success(new SessionDeviceResource($sessionDevice));

        } catch (ModelNotFoundException $th) {
            DB::rollBack();
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::exception($th);
        }
    }
}


