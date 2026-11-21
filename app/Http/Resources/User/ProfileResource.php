<?php

namespace App\Http\Resources\User;

use App\Models\Media\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => Str::contains($this->email, '_') ? Str::before($this->email, '_') : $this->email,
            // 'appKey' => Str::contains($this->email, '_')? Str::after($this->email, '_'): $this->email,
            'roleName' => $this->roles->first()->name ?? '',
            'avatar' => $this->media?->path
            ?? Media::on('tenant')->where('category', 'avatar')->first()?->path
            ?? '',
        ];
    }
}
