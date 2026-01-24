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
use App\Enums\BookedDevice\BookedDeviceEnum;

class EndGroupTimesEditedController extends Controller  implements HasMiddleware
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
        $validated = $request->validate([
            'sessionDeviceId' => 'required|exists:session_devices,id',
            'actualPaidAmount' => 'nullable|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $sessionDevice = SessionDevice::with('bookedDevices')->findOrFail($validated['sessionDeviceId']);


            if (!$sessionDevice) {
                return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
            }

            $totalCost = 0;
            $finishedDevices = [];

            // الخطوة 1: إنهاء جميع الأجهزة وحساب التكلفة الإجمالية
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

            // الخطوة 2: حساب المبلغ الفعلي المدفوع
            $actualPaidTotal = $validated['actualPaidAmount'] ?? $totalCost;

            // الخطوة 3: توزيع المبلغ على الأجهزة
            if ($totalCost > 0 && count($finishedDevices) > 0) {
                $distributedTotal = 0;
                $devicesCount = count($finishedDevices);

                foreach ($finishedDevices as $index => $device) {
                    // حساب نسبة كل جهاز من التكلفة الإجمالية
                    $ratio = $device->period_cost / $totalCost;

                    // للجهاز الأخير: نعطيه الباقي لتجنب مشاكل التقريب
                    if ($index === $devicesCount - 1) {
                        $devicePaidAmount = $actualPaidTotal - $distributedTotal;
                    } else {
                        $devicePaidAmount = round($actualPaidTotal * $ratio, 2);
                        $distributedTotal += $devicePaidAmount;
                    }

                    // تحديث المبلغ المدفوع فقط
                    $device->update([
                        'actual_paid_amount' => $devicePaidAmount
                    ]);
                }
            } else {
                // في حالة التكلفة = 0، نوزع المبلغ بالتساوي
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

                // ترتيب حسب آخر سجل
                $devices = $devices->sortBy('id')->values();

                // إجمالي مبلغ الجهاز (مثلاً 100)
                $deviceTotalAmount = $devices->sum('actual_paid_amount');

                // آخر record فقط
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
            return ApiResponse::error(__('crud.server_error'), $th->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
