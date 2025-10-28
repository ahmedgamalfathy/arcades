<?php

namespace App\Http\Resources\Daily\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Expense\Daily\AllExpenseDailyResource;
use App\Http\Resources\Timer\SessionDevice\Daily\AllSessionIncomeResource;
use App\Http\Resources\Order\Daily\AllOrderIncomeResource;

class ReportDailyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // نحول include إلى مصفوفة
        $includes = collect(explode(',', $request->get('include', '')))->filter()->toArray();

        $data = [
            'dailyId' => $this->id,
            'totalIncome' => $this->total_income ?? "",
            'totalExpense' => $this->total_expense ?? "",
            'totalProfit' => $this->total_profit ?? "",
        ];

        // فقط إذا كانت موجودة في include
        if (in_array('sessions', $includes) && $this->relationLoaded('sessions') && $this->sessions->isNotEmpty()) {
            $data['sessions'] = [
                'total' => $this->sessions->sum(fn($session) =>
                    $session->bookedDevices->sum('period_cost')
                ),
                'data' => AllSessionIncomeResource::collection($this->sessions),
            ];
        }

        if (in_array('orders', $includes) && $this->relationLoaded('orders') && $this->orders->isNotEmpty()) {
            $data['orders'] = [
                'total' => $this->totalOrders(),
                'data' => AllOrderIncomeResource::collection($this->orders),
            ];
        }

        if (in_array('expenses', $includes) && $this->relationLoaded('expenses') && $this->expenses->isNotEmpty()) {
            $data['expenses'] = [
                'total' => $this->totalExpenses(),
                'data' => AllExpenseDailyResource::collection($this->expenses),
            ];
        }

        return $data;
    }
}
