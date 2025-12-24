<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 'id' => $this->id,
            'avatar' => $this->avatar??"",
            'name' => $this->name,
            'email' => $this->email,
            'appKey'=>$this->app_key??"",
        ];
    }
}
