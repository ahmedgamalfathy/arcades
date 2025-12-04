<?php

namespace App\Http\Resources\Expense;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Media\Media;

class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'expenseId' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'date' => Carbon::parse($this->date)->format('Y-m-d')??"",
            'time' => Carbon::parse($this->date)->format('H:i:s')??"",
            'note' => $this->note??"",
            'userName' => $this->user->name,
            'userAvatar'=>$this->user->media->path??Media::on('tenant')->where('category', 'avatar')->first()?->path??"",
        ];
    }
}
