<?php

namespace App\Http\Resources\Daily\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Expense\Daily\AllExpenseDailyResource;
use App\Http\Resources\Timer\SessionDevice\Daily\AllSessionIncomeResource;
use App\Http\Resources\Order\Daily\AllOrderIncomeResource;

class AllReportDailyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // استخراج include وتحويله لمصفوفة
        $includes = collect(explode(',', $request->get('include', '')))
            ->filter()
            ->values()
            ->toArray();

        $data = [
            'dailyId' => $this->id,
        ];

        /** -----------------------------
         * SESSIONS
         * ----------------------------- */
        if (in_array('sessions', $includes) && $this->relationLoaded('sessions') && $this->sessions->isNotEmpty()) {
            $data['sessions'] = [
                'total' => $this->sessions->sum(fn($session) =>
                    $session->bookedDevices->sum('period_cost')
                ),
                'data' => AllSessionIncomeResource::collection($this->sessions),
            ];
        } else {
            $data['sessions'] = [];
        }

        /** -----------------------------
         * ORDERS
         * ----------------------------- */
        if (in_array('orders', $includes) && $this->relationLoaded('orders') && $this->orders->isNotEmpty()) {
            $data['orders'] = [
                'total' => $this->totalOrders(),
                'data' => AllOrderIncomeResource::collection($this->orders),
            ];
        } else {
            $data['orders'] = [];
        }

        /** -----------------------------
         * EXPENSES
         * ----------------------------- */
        if (in_array('expenses', $includes) && $this->relationLoaded('expenses') && $this->expenses->isNotEmpty()) {
            $data['expenses'] = [
                'total' => $this->totalExpenses(),
                'data' => AllExpenseDailyResource::collection($this->expenses),
            ];
        } else {
            $data['expenses'] = [];
        }

        return $data;
    }
}
