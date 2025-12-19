<?php

namespace App\Http\Resources\Expense;

use Carbon\Carbon;
use App\Models\Media\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userAvatarPath = '';
        if ($this->user && $this->user->media_id) {
            $media = DB::connection('tenant')
                ->table('media')
                ->where('id', $this->user->media_id)
                ->first();

            $userAvatarPath =Storage::disk('public')->url($media->path);
        }else{
            $default = DB::connection('tenant')
                ->table('media')
                ->where('category','avatar')
                ->first();
            $userAvatarPath =Storage::disk('public')->url($default->path)??"";
        }
        return [
            'expenseId' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'date' => Carbon::parse($this->date)->format('Y-m-d')??"",
            'time' => Carbon::parse($this->date)->format('H:i:s')??"",
            'note' => $this->note??"",
            'userName' => $this->user->name,
            // 'userAvatar'=>$this->user->avatar_path ?? '',
            'userAvatar'=> $userAvatarPath,

        ];
    }
}
