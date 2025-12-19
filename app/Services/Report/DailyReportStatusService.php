<?php

namespace App\Services\Report;
use Carbon\Carbon;
use App\Models\Order\Order;
use App\Models\Device\Device;
use App\Models\Expense\Expense;
use Illuminate\Support\Collection;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;

class DailyReportStatusService
{
public function getStatusReport(array $data)
{
    $startDate = Carbon::parse($data['startDateTime'])->startOfDay();
    $endDate = Carbon::parse($data['endDateTime'])->endOfDay();
    $search = $data['search'] ?? null;
    $includes = $this->parseIncludes($data['include'] ?? null);

    $report = [];

    // Orders with daily_id in the date range
    if (empty($includes) || in_array('orders', $includes)) {
        $ordersQuery = Order::whereNotNull('daily_id')->whereNull('booked_device_id')
            ->whereBetween('created_at', [$startDate, $endDate])
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

    // External expenses with daily_id in the date range
    if (empty($includes) || in_array('expenses', $includes)) {
        $expensesQuery = Expense::where('type', ExpenseTypeEnum::INTERNAL->value)
            ->whereNotNull('daily_id')
            ->whereBetween('created_at', [$startDate, $endDate])
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

    // Booked devices in the date range
    if (empty($includes) || in_array('sessions', $includes)) {
        $sessions = SessionDevice::whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $sessions->where('name', 'like', "%{$search}%");
            $sessions->orWhereHas('bookedDevices.device', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $sessions = $sessions->get()->map(function($session) {
            return [
                 'id' => $session->id,
                 'name' => $session->name == 'individual' ? $session->bookedDevices->first()->device->name: $session->name,
                 'price' => (($session->bookedDevices?->sum('period_cost') ?? 0) +  ($session->orders?->sum('price') ?? 0)),
                 'date' => Carbon::parse($session->created_at)->format('d-M'),
                 'time' => Carbon::parse($session->created_at)->format('H:i a'),
                 'type' => 'session'
            ];
        });

        $report = array_merge($report, $sessions->toArray());
    }

    // Sort by created_at (optional)
    usort($report, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    return $report;
}

public function reports(array $data)
{
$search = $data['search'] ?? null;
$includes = $this->parseIncludes($data['include'] ?? null);

// Get the daily record to extract start and end date times;
$startDate = Carbon::parse($data['startDateTime'])->startOfDay();
$endDate = Carbon::parse($data['endDateTime'])->endOfDay();

$report = [];

// Orders with the specific daily_id
if (empty($includes) || in_array('orders', $includes)) {
    $ordersQuery = Order::whereNotNull('daily_id')
         ->with('bookedDevice.device')
        // ->whereNull('booked_device_id')
        ->select('id', 'name', 'number', 'price', 'created_at','booked_device_id')
        ->whereBetween('created_at', [$startDate, $endDate]);

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
            'bookedDeviceName'=>$order->bookedDevice?->device?->name,
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
        ->whereNotNull('daily_id')
        ->whereBetween('created_at', [$startDate, $endDate])
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
            'time' => Carbon::parse($expense->created_at)->format('H:i'),
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
                'name' => $session->name == 'individual' ? $session->bookedDevices->first()?->device->name : $session->name,
                'price' => (($session->bookedDevices?->sum('period_cost') ?? 0) + ($session->orders?->sum('price') ?? 0)),
                'date' => Carbon::parse($session->created_at)->format('d-M'),
                'time' => Carbon::parse($session->created_at)->format('H:i a'),
                'type' => 'session'
        ];
    });

    $report = array_merge($report, $sessions->toArray());
}

// Sort by date descending
// usort($report, function($a, $b) {
//     return strtotime($b['date'] . ' ' . $b['time']) - strtotime($a['date'] . ' ' . $a['time']);
// });
usort($report, function($a, $b) {
    try {
        $dateA = Carbon::createFromFormat('d-M H:i a', $a['date'] . ' ' . $a['time']);
        $dateB = Carbon::createFromFormat('d-M H:i a', $b['date'] . ' ' . $b['time']);
        return $dateB->timestamp - $dateA->timestamp;
    } catch (\Exception $e) {
        return 0;
    }
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
