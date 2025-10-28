<?php

namespace App\Http\Resources\Expense\Daily;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllExpenseDailyResource extends JsonResource
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
            'date' => Carbon::parse($this->date)->format('Y-m-d'),
            'timeCreated' =>Carbon::parse($this->created_at)->format('H:i')
        ];
    }
}
