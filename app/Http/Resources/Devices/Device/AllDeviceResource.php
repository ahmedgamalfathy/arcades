<?php

namespace App\Http\Resources\Devices\Device;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'devices' => DeviceResource::collection($this->resource->items()),
            'perPage'      => $this->resource->count(),
            'nextPageUrl'  => $this->resource->nextPageUrl(),
            'prevPageUrl'  => $this->resource->previousPageUrl(),
        ];
    }
}
