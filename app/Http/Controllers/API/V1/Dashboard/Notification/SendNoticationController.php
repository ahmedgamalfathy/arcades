<?php

namespace App\Http\Controllers\API\V1\Dashboard\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Notifications\BookedDeviceStatusNotification;
use App\Models\User;
use App\Models\Setting\Param\Param;
use Carbon\Carbon;
class SendNoticationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $now = Carbon::now();
        // $user = User::findOrFail(auth('api')->id());
        $users=User::where('is_active',1)->get();
        $defaultTimeNotification = Param::where('parameter_order',1)->first()->value('type');
        $bookedDevices = BookedDevice::where('status', '!=', 'finished') 
        ->whereBetween('end_date_time', [
        $now->copy()->addMinutes($defaultTimeNotification), 
        $now->copy()->addMinutes($defaultTimeNotification + 1)  
        ])->get();
        $expiredBookedDevices = BookedDevice::where('status', '!=', 'finished') 
        ->where('end_date_time', '<=', $now)->get();
        foreach ($users as $user) {
            foreach ($bookedDevices as $booked) {
                $user->notify(new BookedDeviceStatusNotification([
                        "sessionDevice" => $booked->sessionDevice->id,
                        "deviceTypeName" => $booked->deviceType->name,
                        "deviceTimeName"=>$booked->deviceTime->name,
                        "deviceName"=>$booked->device->name,
                        "bookedDeviceId" => $booked->id,
                        "message"=>"متبقى على الجهاز{$defaultTimeNotification} دقائق",
                        "userId" => $user->id,
                ]));
            }
        }
        foreach ($users as $user) {
                foreach ($expiredBookedDevices as $booked) {
                    $user->notify(new BookedDeviceStatusNotification([
                            "sessionDevice" => $booked->sessionDevice->id,
                            "deviceTypeName" => $booked->deviceType->name,
                            "deviceTimeName"=>$booked->deviceTime->name,
                            "deviceName"=>$booked->device->name,
                            "bookedDeviceId" => $booked->id,
                            "message"=>"انتهى الوقت المتبقى على الجهاز",
                            "userId" => $user->id,
                    ]));
            }
        }
    return ApiResponse::success([],'Notification sent successfully!');
    }
}
