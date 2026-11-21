<?php

namespace App\Http\Resources\Daily\V2;

use App\Enums\BookedDevice\BookedDeviceEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

class DailyRangeOfDateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // حساب الدخل والمصروفات
        $totalIncome = $this->getTotalIncome();
        $totalExpense = $this->getTotalExpense();
        $totalProfit = $this->getTotalProfit($totalIncome, $totalExpense);
        $createdBy = Activity::where('log_name', 'Daily')->where('event', 'created')
            ->with('causer')
            ->where('subject_id', $this->id)->first();

        return [
            'dailyId' => $this->id,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'totalProfit' => $totalProfit,
            'startDate' => $this->start_date_time ? Carbon::parse($this->start_date_time)->format('d-m-Y') : '',
            'endDate' => $this->end_date_time ? Carbon::parse($this->end_date_time)->format('d-m-Y') : '',
            'startTime' => $this->start_date_time ? Carbon::parse($this->start_date_time)->format('H:i:s') : '',
            'endTime' => $this->end_date_time ? Carbon::parse($this->end_date_time)->format('H:i:s') : '',
            'duration' => ($this->start_date_time && $this->end_date_time)
                ? Carbon::parse($this->start_date_time)->diff(Carbon::parse($this->end_date_time))->format('%H:%I:%S')
                : '',
            'isActive' => $this->end_date_time === null,
            'createdBy' => $createdBy?->causer?->name ?? '',
        ];
    }

    protected function getTotalIncome(): float
    {
        if ($this->end_date_time !== null) {
            return round($this->total_income, 2);
        }

        return $this->calculateTotalIncome();
    }

    protected function getTotalExpense(): float
    {
        if ($this->end_date_time !== null) {
            return round($this->total_expense, 2);
        }

        return $this->calculateTotalExpense();
    }

    protected function getTotalProfit(float $income, float $expense): float
    {
        if ($this->end_date_time !== null) {
            return round($this->total_profit, 2);
        }

        return round($income - $expense, 2);
    }

    protected function calculateTotalIncome(): float
    {
        $sessionsTotal = 0;

        if ($this->relationLoaded('sessions')) {
            $sessionsTotal = $this->sessions->sum(function ($session) {
                return $session->bookedDevices
                    ->where('status', BookedDeviceEnum::FINISHED->value)
                    ->groupBy('device_id')
                    ->map(fn ($devices) => $devices->sortByDesc('id')->first())
                    ->sum(fn ($device) => (float) ($device->actual_paid_amount ?? 0));
            });
        }

        $ordersTotal = $this->relationLoaded('orders') ? $this->totalOrders() : 0;

        return round($sessionsTotal + $ordersTotal, 2);
    }

    protected function calculateTotalExpense(): float
    {
        $total = $this->relationLoaded('expenses') ? $this->totalExpenses() : 0;

        return round($total, 2);
    }
}
