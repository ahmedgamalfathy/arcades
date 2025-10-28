<?php

namespace App\Http\Resources\Daily;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
    use App\Http\Resources\Expense\Daily\AllExpenseDailyResource;
use App\Http\Resources\Timer\SessionDevice\Daily\AllSessionIncomeResource;
use App\Http\Resources\Order\Daily\AllOrderIncomeResource;

class DailyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $includes = collect(explode(',', $request->get('include', '')))
            ->filter()
            ->values()
            ->toArray();
        $data= [
            'dailyId' => $this->id,
            'totalIncome' => $this->total_income??"",
            'totalExpense' => $this->total_expense??"",
            'totalProfit' => $this->total_profit??"",
            ];
            if (in_array('sessions', $includes) && $this->relationLoaded('sessions')) {
            $data['sessions'] = [
                'total' => $this->sessions->sum(function($session) {
                    return $session->bookedDevices->sum('period_cost');
                }),
                'data' => AllSessionIncomeResource::collection($this->sessions)
            ];
            }

            if (in_array('orders', $includes) && $this->relationLoaded('orders')) {
            $data['orders'] = [
            'total' => $this->totalOrders(),
            'data' => AllOrderIncomeResource::collection($this->orders)
            ];
            }
            if (in_array('expenses', $includes) && $this->relationLoaded('expenses')) {
            $data['expenses'] = [
            'total' => $this->totalExpenses(),
            'data' => AllExpenseDailyResource::collection($this->expenses)
            ];
            }
      return $data;
    }
}
