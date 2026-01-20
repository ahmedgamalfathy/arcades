<?php

namespace App\Http\Resources\Daily;

use App\Enums\Order\OrderTypeEnum;
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

        // حساب الدخل والمصروفات
        $totalIncome = round($this->total_income ?? $this->calculateTotalIncome(), 2);
        $totalExpense = round($this->total_expense ?? $this->calculateTotalExpense(), 2);

        // حساب الربح:
        // لو اليومية متقفلتش (end_date_time == null) احسب live
        // لو مقفولة خد المحفوظ أو احسب
        if ($this->end_date_time == null) {
            // اليومية شغالة، احسب live
            $totalProfit = round($totalIncome - $totalExpense, 2);
        } else {
            // اليومية مقفولة، خد المحفوظ أو احسب
            $totalProfit = round($this->total_profit ?? ($totalIncome - $totalExpense), 2);
        }

        $data = [
            'dailyId' => $this->id,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'totalProfit' => $totalProfit,
            'isActive' => $this->end_date_time == null, // مؤشر لو اليومية شغالة
        ];

        if (in_array('sessions', $includes) && $this->relationLoaded('sessions')) {
            // احسب الإجمالي (live للشغال + المحفوظ للمقفول)
            $sessionsTotal = $this->sessions->sum(function($session) {
                return $session->bookedDevices->where('status', 0)->sum(function($bookedDevice) {
                    $cost = ($bookedDevice->status != 0)
                        ? $bookedDevice->calculatePrice()
                        : ($bookedDevice->actual_paid_amount ?? 0);
                    return round($cost, 2);
                });
            });

            $data['sessions'] = [
                'total' => round($sessionsTotal, 2),
                'data' => AllSessionIncomeResource::collection($this->sessions)
            ];
        }

        if (in_array('orders', $includes) && $this->relationLoaded('orders')) {
            $data['orders'] = [
                'total' => round($this->totalOrders(), 2),
                'data' => AllOrderIncomeResource::collection($this->orders)
            ];
        }

        if (in_array('expenses', $includes) && $this->relationLoaded('expenses')) {
            $data['expenses'] = [
                'total' => round($this->totalExpenses(), 2),
                'data' => AllExpenseDailyResource::collection($this->expenses)
            ];
        }

        return $data;
    }

    /**
     * حساب إجمالي الدخل (live للشغال + محفوظ للمقفول)
     */
    protected function calculateTotalIncome(): float
    {
        $sessionsTotal = 0;

        if ($this->relationLoaded('sessions')) {
            $sessionsTotal = $this->sessions->sum(function($session) {
                return $session->bookedDevices->where('status', 0)->sum(function($bookedDevice) {
                    $cost = ($bookedDevice->status != 0)
                        ? $bookedDevice->calculatePrice()
                        : ($bookedDevice->actual_paid_amount ?? 0);
                    return round($cost, 2);
                });
            });
        }

        $ordersTotal = $this->relationLoaded('orders') ? $this->totalOrders() : 0;
        return round($sessionsTotal + $ordersTotal, 2);
    }

    /**
     * حساب إجمالي المصروفات
     */
    protected function calculateTotalExpense(): float
    {
        $total = $this->relationLoaded('expenses') ? $this->totalExpenses() : 0;
        return round($total, 2);
    }
}
