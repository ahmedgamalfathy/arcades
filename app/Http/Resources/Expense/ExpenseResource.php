<?php

namespace App\Http\Resources\Expense;

use Carbon\Carbon;
use Illuminate\Http\Request;
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
        return [
            'ExpenseId' => $this->id,
            'userName' => $this->user->name,
            'name' => $this->name,
            'price' => $this->price,
            'date' => Carbon::parse($this->date)->format('Y-m-d'),
            'note' => $this->note??"",
        ];
    }
}
