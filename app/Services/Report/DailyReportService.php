<?php

namespace App\Services\Report;
use Carbon\Carbon;
use App\Models\Daily\Daily;
use App\Models\Media\Media;
use Illuminate\Support\Collection;

class DailyReportService
{
    public function getReport(array $data): array
    {
        $startDate = Carbon::parse($data['startDateTime'])->startOfDay();
        $endDate = Carbon::parse($data['endDateTime'])->endOfDay();
        $search = $data['search'] ?? null;
        $includes = $this->parseIncludes($data['include'] ?? null);

        $dailies = $this->fetchDailies($startDate, $endDate, $search, $includes);

        $stats = $this->calculateStats($dailies);
        $mostRequested = $this->getMostRequestedProducts($dailies);
        $mostUsedDevices = $this->getMostUsedDevices($dailies);
        $percentages = $this->calculatePercentages($stats);

        return [
            'stats' => $stats,
            'mostRequested' => $mostRequested,
            'mostUsedBookedDevice' => $mostUsedDevices,
            'percentage' => $percentages,
        ];
    }
    public function getStatusReport(array $data): Collection
    {
        $startDate = Carbon::parse($data['startDateTime'])->startOfDay();
        $endDate = Carbon::parse($data['endDateTime'])->endOfDay();
        $search = $data['search'] ?? null;
        $includes = !empty($data['include'])
        ? array_filter(array_map('trim', explode(',', $data['include'])))
        : [];
       return $this->fetchDailies($startDate, $endDate, $search, $includes);
    }


    private function parseIncludes(?string $includeParam): array
    {
        if (empty($includeParam)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $includeParam)));
    }

    private function fetchDailies(Carbon $startDate, Carbon $endDate, ?string $search, array $includes): Collection
    {
        $query = Daily::query()
            ->where('start_date_time', '>=', $startDate)
            ->where('start_date_time', '<=', $endDate);

        if ($search && !empty($includes)) {
            $query->where(function($q) use ($search, $includes) {
                $this->applySearchFilters($q, $search, $includes);
            });
        }

        // تحميل العلاقات
        if (!empty($includes)) {
            $this->loadRelations($query, $includes, $search);
        }

        return $query->orderBy('start_date_time', 'asc')->get();
    }

    /**
     * تطبيق فلاتر البحث
     */
    private function applySearchFilters($query, string $search, array $includes): void
    {
        if (in_array('orders', $includes)) {
            $query->orWhereHas('orders', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhere('price', 'like', "%{$search}%");
            });
        }

        if (in_array('sessions', $includes)) {
            $query->orWhereHas('sessions', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('bookedDevices.device', function($deviceQuery) use ($search) {
                        $deviceQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array('expenses', $includes)) {
            $query->orWhereHas('expenses', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('price', 'like', "%{$search}%");
            });
        }
    }

    /**
     * تحميل العلاقات المطلوبة
     */
    private function loadRelations($query, array $includes, ?string $search): void
    {
        foreach ($includes as $include) {
            if ($include === 'orders') {
                $this->loadOrders($query, $search);
            } elseif ($include === 'sessions') {
                $this->loadSessions($query, $search);
            } elseif ($include === 'expenses') {
                $this->loadExpenses($query, $search);
            }
        }
    }

    /**
     * تحميل الطلبات
     */
    private function loadOrders($query, ?string $search): void
    {
        if ($search) {
            $query->with(['orders' => function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhere('price', 'like', "%{$search}%");
            }]);
        } else {
            $query->with('orders.items.product');
        }
    }

    /**
     * تحميل الجلسات
     */
    private function loadSessions($query, ?string $search): void
    {
        if ($search) {
            $query->with(['sessions' => function($q) use ($search) {
                $q->where(function($sessionQ) use ($search) {
                    $sessionQ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('bookedDevices.device', function($deviceQuery) use ($search) {
                            $deviceQuery->where('name', 'like', "%{$search}%");
                        });
                })->with('bookedDevices.device');
            }]);
        } else {
            $query->with('sessions.bookedDevices.device');
        }
    }

    /**
     * تحميل المصروفات
     */
    private function loadExpenses($query, ?string $search): void
    {
        if ($search) {
            $query->with(['expenses' => function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('price', 'like', "%{$search}%");
            }]);
        } else {
            $query->with('expenses');
        }
    }

    /**
     * حساب الإحصائيات
     */
    private function calculateStats(Collection $dailies): array
    {
        $totalExpense = $dailies->sum(fn($daily) => $daily->expenses->sum('price'));
        $totalOrders = $dailies->sum(fn($daily) => $daily->orders->sum('price'));

        $totalSessions = $dailies->sum(function($daily) {
            return $daily->sessions->sum(function($session) {
                return $session->bookedDevices->sum('period_cost');
            });
        });

        $totalIncome = $totalOrders + $totalSessions;
        $totalProfit = $totalIncome - $totalExpense;

        return [
            'totalIncome' => round($totalIncome, 2),
            'totalOrders' => round($totalOrders, 2),
            'totalSessions' => round($totalSessions, 2),
            'totalExpense' => round($totalExpense, 2),
            'totalProfit' => round($totalProfit, 2),
        ];
    }

    /**
     * المنتجات الأكثر طلباً
     */
    private function getMostRequestedProducts(Collection $dailies): Collection
    {
        return $dailies
            ->flatMap(fn($daily) => $daily->orders)
            ->flatMap(fn($order) => $order->items ?? [])
            ->filter(fn($item) => $item->product)
            ->groupBy(fn($item) => $item->product->name)
            ->map(fn($items, $productName) => [
                'productPath'=>$items->first()->product->media?->path??Media::where('category','products')->first()->path??'',
                'productName' => $productName,
                'totalOrders' => $items->count(),
                'totalQuantity' => $items->sum('quantity'),
            ])
            ->sortByDesc('totalOrders')
            ->values();
    }

    /**
     * الأجهزة الأكثر استخداماً
     */
    private function getMostUsedDevices(Collection $dailies): Collection
    {
        return $dailies
            ->flatMap(fn($daily) => $daily->sessions ?? [])
            ->flatMap(fn($session) => $session->bookedDevices ?? [])
            ->filter(fn($bookedDevice) => $bookedDevice->device)
            ->groupBy(fn($bookedDevice) => $bookedDevice->device->name)
            ->map(fn($group, $deviceName) => [
                'devicePath'=>$group->first()?->device->media?->path,
                'deviceType'=>$group->first()?->device->deviceType?->name,
                'deviceName' => $deviceName,
                'totalHours' => round(
                    $group->sum(fn($bookedDevice) => $bookedDevice->calculateUsedSeconds()) / 3600,
                    2
                ),
                'totalBookings' => $group->count(),
            ])
            ->sortByDesc('totalHours')
            ->values();
    }

    /**
     * حساب النسب المئوية
     */
    private function calculatePercentages(array $stats): array
    {
        $total = $stats['totalOrders'] + $stats['totalSessions'] + $stats['totalExpense'];

        if ($total <= 0) {
            return [
                'orderPercentage' => 0,
                'sessionPercentage' => 0,
                'expensePercentage' => 0,
            ];
        }

        return [
            'orderPercentage' => round(($stats['totalOrders'] / $total) * 100, 2),
            'sessionPercentage' => round(($stats['totalSessions'] / $total) * 100, 2),
            'expensePercentage' => round(($stats['totalExpense'] / $total) * 100, 2),
        ];
    }
}
