<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer;

use Book;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Timer\BookedDeviceService;
use App\Http\Resources\Timer\SessionDevice\AllBookedDeviceSessionCollection;


class BookedDeviceController extends Controller
{
    public function __construct(public BookedDeviceService $bookedDeviceService)
    {
        //
    }
    public function allBookedDevices(Request $request)
    {
         $bookedDevices = $this->bookedDeviceService->allBookedDevices($request);
         return ApiResponse::success(  new AllBookedDeviceSessionCollection($bookedDevices));
    }
}
