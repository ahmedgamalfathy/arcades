<?php

namespace App\Http\Controllers\API\V1\Dashboard\Timer;

use Book;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Services\Timer\BookedDeviceService;
use App\Http\Resources\Timer\SessionDevice\AllBookedDeviceSessionCollection;


class BookedDeviceController extends Controller implements HasMiddleware
{
    public function __construct(public BookedDeviceService $bookedDeviceService)
    {
        //
    }
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('permission:products', only:['index']),
            new Middleware('permission:edit_product', only:['edit']),
            new Middleware('permission:destroy_product', only:['destroy']),
            new Middleware('tenant'),
        ];
    }
    public function allBookedDevices(Request $request)
    {
         $bookedDevices = $this->bookedDeviceService->allBookedDevices($request);
         return ApiResponse::success(  new AllBookedDeviceSessionCollection($bookedDevices));
    }
}
