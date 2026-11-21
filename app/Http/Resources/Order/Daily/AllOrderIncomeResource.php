<?php

namespace App\Http\Resources\Order\Daily;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllOrderIncomeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'orderId' => $this->id,
            'name' => $this->name ?? $this->bookedDevice->device->name ?? '',
            'price' => $this->price,
            'createdAt' => Carbon::parse($this->created_at)->format('d/m/Y'),
            'timeCreated' => Carbon::parse($this->created_at)->format('H:i'),
        ];
    }
}
