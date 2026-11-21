<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Timer\SessionDevice\AllBookedDeviceSessionCollection;
use App\Services\Timer\BookedDeviceService;
use Illuminate\Http\Request;

class BookedDeviceController extends Controller
{
    public function __construct(public BookedDeviceService $bookedDeviceService)
    {
        //
    }

    public function allBookedDevices(Request $request)
    {
        $bookedDevices = $this->bookedDeviceService->allBookedDevices($request);

        return ApiResponse::success(new AllBookedDeviceSessionCollection($bookedDevices));
    }
}
