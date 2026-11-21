<?php

namespace App\Http\Resources\Expense;

use App\Models\Media\Media;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userAvatarPath = null;
        if ($this->user && $this->user->media_id) {
            $media = DB::connection('tenant')
                ->table('media')
                ->where('id', $this->user->media_id)
                ->first();

            $userAvatarPath = $media->path ?? '';
        }

        // $mediaPath = DB::connection('tenant')
        // ->table('media')
        // ->where('id', $this->user->media_id)
        // ->value('path');
        return [
            'expenseId' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'date' => Carbon::parse($this->date)->format('d/m/Y') ?? '',
            'time' => Carbon::parse($this->date)->format('H:i:s') ?? '',
            'note' => $this->note ?? '',
            'userName' => $this->user->name,
            'userAvatar' => $userAvatarPath ? asset('storage/'.$userAvatarPath) : Media::where('category', 'avatar')->first()->path,
            // 'userAvatar' =>  $userAvatarPath ? Storage::disk('public')->url($userAvatarPath) :Media::where('category', 'avatar')->first()->path
        ];
    }
}
