<?php

namespace App\Http\Resources\User\ForgetPassword;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SendCodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
