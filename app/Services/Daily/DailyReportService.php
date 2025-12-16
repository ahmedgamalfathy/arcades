<?php

namespace App\Services\Daily;

use Carbon\Carbon;
use App\Models\Order\Order;
use App\Models\Expense\Expense;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Models\Daily\Daily;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class DailyReportService
{
public function dailyReport(array $data)
{
    $dailyId = $data['dailyId'];
    $search = $data['search'] ?? null;
    $includes = $this->parseIncludes($data['include'] ?? null);

    // Get the daily record to extract start and end date times
    $daily = Daily::findOrFail($dailyId);
    $startDate = Carbon::parse($daily->start_date_time);
    $endDate = Carbon::parse($daily->end_date_time);

    $report = [];

    // Orders with the specific daily_id
    if (empty($includes) || in_array('orders', $includes)) {
        $ordersQuery = Order::where('daily_id', $dailyId)
            ->select('id', 'name', 'number', 'price', 'created_at');

        if ($search) {
            $ordersQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%");
            });
        }

        $orders = $ordersQuery->get()->map(function($order) {
            return [
                'id' => $order->id,
                'name' => $order->name,
                'price' => $order->price,
                'date' => Carbon::parse($order->created_at)->format('d-M'),
                'time' => Carbon::parse($order->created_at)->format('H:i a'),
                'type' => 'order'
            ];
        });

        $report = array_merge($report, $orders->toArray());
    }

    // Expenses with the specific daily_id
    if (empty($includes) || in_array('expenses', $includes)) {
        $expensesQuery = Expense::where('type', ExpenseTypeEnum::INTERNAL->value)
            ->where('daily_id', $dailyId)
            ->select('id', 'name', 'price', 'created_at');

        if ($search) {
            $expensesQuery->where('name', 'like', "%{$search}%");
        }

        $expenses = $expensesQuery->get()->map(function($expense) {
            return [
                'id' => $expense->id,
                'name' => $expense->name,
                'price' => $expense->price,
                'date' => Carbon::parse($expense->created_at)->format('d-M'),
                'time' => Carbon::parse($expense->created_at)->format('H:i a'),
                'type' => 'expense'
            ];
        });

        $report = array_merge($report, $expenses->toArray());
    }

    // Sessions within the daily's date range
    if (empty($includes) || in_array('sessions', $includes)) {
        $sessions = SessionDevice::whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $sessions->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('bookedDevices.device', function($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
            });
        }

        $sessions = $sessions->get()->map(function($session) {
            return [
                 'id' => $session->id,
                 'name' => $session->name == 'individual' ? $session->bookedDevices->first()->device->name : $session->name,
                 'price' => (($session->bookedDevices?->sum('period_cost') ?? 0) + ($session->orders?->sum('price') ?? 0)),
                 'date' => Carbon::parse($session->created_at)->format('d-M'),
                 'time' => Carbon::parse($session->created_at)->format('H:i a'),
                 'type' => 'session'
            ];
        });

        $report = array_merge($report, $sessions->toArray());
    }

    // Sort by date descending
    usort($report, function($a, $b) {
        return strtotime($b['date'] . ' ' . $b['time']) - strtotime($a['date'] . ' ' . $a['time']);
    });

    return $report;
}
private function parseIncludes(?string $includeParam): array
{
        if (empty($includeParam)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $includeParam)));
}
public function getMonthlyChartData(int $dailyId)
{
    $daily = Daily::findOrFail($dailyId);
    $startDate = Carbon::parse($daily->start_date_time);
    $endDate = Carbon::parse($daily->end_date_time);
    $startOfMonth = Carbon::parse($startDate)->startOfMonth();
    $endOfMonth = Carbon::parse($endDate)->endOfMonth();

    // Initialize result array with all days of the month
    $chartData = [];
    $currentDate = $startOfMonth->copy();

    while ($currentDate <= $endOfMonth) {
        $day = $currentDate->format('d');
        $chartData[$day] = [
            'date' => $currentDate->format('Y-m-d'),
            'orders' => 0,
            'expenses' => 0,
            'sessions' => 0,
        ];
        $currentDate->addDay();
    }

    // Get Orders grouped by day (with specific daily_id)
    $orders = Order::where('daily_id', $dailyId)
        ->whereNull('booked_device_id')
        ->selectRaw('DATE(created_at) as date, SUM(price) as total')
        ->groupBy(DB::raw('DATE(created_at)'))
        ->get();

    foreach ($orders as $order) {
        $day = Carbon::parse($order->date)->format('d');
        if (isset($chartData[$day])) {
            $chartData[$day]['orders'] = round((float) $order->total, 2);
        }
    }

    // Get Expenses grouped by day (with specific daily_id)
    $expenses = Expense::where('type', ExpenseTypeEnum::INTERNAL->value)
        ->where('daily_id', $dailyId)
        ->selectRaw('DATE(created_at) as date, SUM(price) as total')
        ->groupBy(DB::raw('DATE(created_at)'))
        ->get();

    foreach ($expenses as $expense) {
        $day = Carbon::parse($expense->date)->format('d');
        if (isset($chartData[$day])) {
            $chartData[$day]['expenses'] = round((float) $expense->total, 2);
        }
    }

    // Get Sessions grouped by day (within daily's date range)
    $sessions = SessionDevice::where('daily_id', $dailyId)
        ->with('bookedDevices')
        ->get();

    // Group sessions by date and calculate total period_cost
    foreach ($sessions as $session) {
        $day = Carbon::parse($session->created_at)->format('d');
        $totalCost = $session->bookedDevices->sum('period_cost');
        $totalCost +=($session->orders?->sum('price') ?? 0);
        if (isset($chartData[$day])) {
            $chartData[$day]['sessions'] += round((float) $totalCost, 2);
        }
    }

    // Round sessions totals after accumulation
    foreach ($chartData as $day => $data) {
        $chartData[$day]['sessions'] = round($data['sessions'], 2);
    }

    $chartData = array_filter($chartData, function($day) {
        return $day['orders'] > 0 || $day['expenses'] > 0 || $day['sessions'] > 0;
    });

    return $chartData;
}
}
