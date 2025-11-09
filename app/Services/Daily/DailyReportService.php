<?php

namespace App\Services\Daily;

use Carbon\Carbon;
use App\Models\Order\Order;
use App\Models\Expense\Expense;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Models\Daily\Daily;

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
            ->whereNull('booked_device_id')
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
}