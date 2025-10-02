<?php

namespace App\Http\Resources\Maintenance;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'MaintenanceId' => $this->id,
            'userName'=>$this->user->name ??"",
            'title' => $this->title,
            'price' => $this->price,
            'date' => Carbon::parse($this->date)->format('Y-m-d'),
            'note' => $this->note??"",
        ];
    }
}
