<?php

namespace App\Http\Resources\Timer\SessionDevice;

use App\Http\Resources\Timer\BookedDevice\AllBookedDeviceResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AllBookedDeviceSessionCollection extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'sessions' => AllBookedDeviceResource::collection($this->collection),
            'perPage' => $this->collection->count(),
            'nextCursor' => optional($this->resource)->nextCursor()?->encode(),
            'prevCursor' => optional($this->resource)->previousCursor()?->encode(),
        ];
    }
}
