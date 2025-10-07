<?php

namespace App\Http\Resources\User;

use App\Models\User;
use App\Models\Media\Media;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;


class PorfileResource extends JsonResource
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
            'email' => Str::contains($this->email, '_')? Str::before($this->email, '_'): $this->email,
            // 'appKey' => Str::contains($this->email, '_')? Str::after($this->email, '_'): $this->email,
            'roleName' => $this->roles->first()->name??"",
            'avatar' => $this->media?->path
            ?? Media::on('tenant')->where('category', 'avatar')->first()?->path
            ?? "",
        ];
    }
}
