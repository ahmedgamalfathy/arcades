<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserResource extends JsonResource
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
            'email' => Str::contains($this->email, '_') ? Str::before($this->email, '_') : $this->email,
            'appKey' => Str::contains($this->email, '_') ? Str::after($this->email, '_') : $this->email,
            'roleId' => $this->roles->first()->id ?? '',
            'isActive' => $this->is_active,
            'avatar' => $this->media->path ?? '',
        ];
    }
}
