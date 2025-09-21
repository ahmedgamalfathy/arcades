<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {


        return [
            'userId' => $this->id,
            'name' => $this->name,
            "email"=> $this->email,
            'isActive' => $this->is_active,
            'avatar' => $this->avatar,
            'roleName' => $this->roles->first()->name??'guest',
        ];
    }
}
