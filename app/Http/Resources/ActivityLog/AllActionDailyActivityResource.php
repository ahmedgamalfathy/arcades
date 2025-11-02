<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ActivityLog\DailyActivityResource;
use App\Http\Resources\ActivityLog\SessionActivityResource;
use App\Http\Resources\ActivityLog\OrderActivityResouce;
use App\Http\Resources\ActivityLog\ExpenseActivityResource;
use App\Http\Resources\ActivityLog\BookedDeviceActivityResource;

class AllActionDailyActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'daily' => DailyActivityResource::collection($this['daily']),
            'sessions' => SessionActivityResource::collection($this['sessions']),
            'orders' => OrderActivityResouce::collection($this['orders']),
            'orderItems' => OrderItemActivityResource::collection($this['orderItems']),
            'expenses' => ExpenseActivityResource::collection($this['expenses']),
            'bookedDevices' => BookedDeviceActivityResource::collection($this['bookedDevices']),
        ];
    }
}
