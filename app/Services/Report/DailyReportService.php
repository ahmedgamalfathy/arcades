<?php

namespace App\Services\Report;

use Carbon\Carbon;
use App\Models\Daily\Daily;
use App\Models\Media\Media;
use Illuminate\Support\Collection;

class DailyReportService
{
    /**
     * Get full report with stats, most requested products, and most used devices
     */
    public function getReport(array $data): array
    {
        $startDate = Carbon::parse($data['startDateTime'])->startOfDay();
        $endDate = Carbon::parse($data['endDateTime'])->endOfDay();
        $search = $data['search'] ?? null;
        $includes = $this->parseIncludes($data['include'] ?? null);
        $dailies = $this->fetchDailies($startDate, $endDate, $search, $includes);

        $stats = $this->calculateStats($dailies, $startDate, $endDate);
        $mostRequested = $this->getMostRequestedProducts($dailies, $startDate, $endDate);
        $mostUsedDevices = $this->getMostUsedDevices($dailies, $startDate, $endDate);
        $percentages = $this->calculatePercentages($stats);

        return [
            'stats' => $stats,
            'mostRequested' => $mostRequested,
            'mostUsedBookedDevice' => $mostUsedDevices,
            'percentage' => $percentages,
        ];
    }

    /**
     * Get status report (returns collection of dailies)
     */
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

    /**
     * Parse include parameter to array
     */
    private function parseIncludes(?string $includeParam): array
    {
        if (empty($includeParam)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $includeParam)));
    }

    /**
     * Fetch dailies with date filters and includes
     */
    private function fetchDailies(Carbon $startDate, Carbon $endDate, ?string $search, array $includes): Collection
    {
        $query = Daily::query()
            ->where('start_date_time', '>=', $startDate)
            ->where('start_date_time', '<=', $endDate);

        if ($search && !empty($includes)) {
            $query->where(function($q) use ($search, $includes, $startDate, $endDate) {
                $this->applySearchFilters($q, $search, $includes, $startDate, $endDate);
            });
        }

        // تحميل العلاقات
        if (!empty($includes)) {
            $this->loadRelations($query, $includes, $search, $startDate, $endDate);
        }

        return $query->orderBy('start_date_time', 'asc')->get();
    }

    /**
     * تطبيق فلاتر البحث مع فلترة التاريخ
     */
    private function applySearchFilters($query, string $search, array $includes, Carbon $startDate, Carbon $endDate): void
    {
        if (in_array('orders', $includes)) {
            $query->orWhereHas('orders', function($q) use ($search, $startDate, $endDate) {
                $q->where(function($searchQuery) use ($search) {
                        $searchQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('number', 'like', "%{$search}%")
                            ->orWhere('price', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array('sessions', $includes)) {
            $query->orWhereHas('sessions', function($q) use ($search, $startDate, $endDate) {
                // $q->whereBetween('created_at', [$startDate, $endDate])
                    $q->where(function($searchQuery) use ($search) {
                        $searchQuery->where('name', 'like', "%{$search}%")
                            ->orWhereHas('bookedDevices.device', function($deviceQuery) use ($search) {
                                $deviceQuery->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if (in_array('expenses', $includes)) {
            $query->orWhereHas('expenses', function($q) use ($search, $startDate, $endDate) {
                // $q->whereBetween('date', [$startDate, $endDate])
                    $q->where(function($searchQuery) use ($search) {
                        $searchQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('price', 'like', "%{$search}%");
                    });
            });
        }
    }

    /**
     * تحميل العلاقات المطلوبة مع فلترة التاريخ
     */
    private function loadRelations($query, array $includes, ?string $search, Carbon $startDate, Carbon $endDate): void
    {
        foreach ($includes as $include) {
            if ($include === 'orders') {
                $this->loadOrders($query, $search, $startDate, $endDate);
            } elseif ($include === 'sessions') {
                $this->loadSessions($query, $search, $startDate, $endDate);
            } elseif ($include === 'expenses') {
                $this->loadExpenses($query, $search, $startDate, $endDate);
            }
        }
    }

    /**
     * تحميل الطلبات مع فلترة التاريخ
     */
    private function loadOrders($query, ?string $search, Carbon $startDate, Carbon $endDate): void
    {
        if ($search) {
            $query->with(['orders' => function($q) use ($search, $startDate, $endDate) {
                // $q->whereBetween('created_at', [$startDate, $endDate])
                    $q->where(function($searchQuery) use ($search) {
                        $searchQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('number', 'like', "%{$search}%")
                            ->orWhere('price', 'like', "%{$search}%");
                    })
                    ->with('items.product');
            }]);
        } else {
            $query->with(['orders' => function($q) use ($startDate, $endDate) {
                // $q->whereBetween('created_at', [$startDate, $endDate])
                    $q->with('items.product');
            }]);
        }
    }

    /**
     * تحميل الجلسات مع فلترة التاريخ
     */
    private function loadSessions($query, ?string $search, Carbon $startDate, Carbon $endDate): void
    {
        if ($search) {
            $query->with(['sessions' => function($q) use ($search, $startDate, $endDate) {
                // $q->whereBetween('created_at', [$startDate, $endDate])
                    $q->where(function($sessionQ) use ($search) {
                        $sessionQ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('bookedDevices.device', function($deviceQuery) use ($search) {
                                $deviceQuery->where('name', 'like', "%{$search}%");
                            });
                    })
                    ->with('bookedDevices.device');
            }]);
        } else {
            $query->with(['sessions' => function($q) use ($startDate, $endDate) {
                // $q->whereBetween('created_at', [$startDate, $endDate])
                    $q->with('bookedDevices.device');
            }]);
        }
    }

    /**
     * تحميل المصروفات مع فلترة التاريخ
     */
    private function loadExpenses($query, ?string $search, Carbon $startDate, Carbon $endDate): void
    {
        if ($search) {
            $query->with(['expenses' => function($q) use ($search, $startDate, $endDate) {
                // $q->whereBetween('created_at', [$startDate, $endDate])
                    $q->where(function($searchQuery) use ($search) {
                        $searchQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('price', 'like', "%{$search}%");
                    });
            }]);
        } else {
            $query->with(['expenses' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            }]);
        }
    }

    /**
     * حساب الإحصائيات مع فلترة التاريخ
     */
    private function calculateStats(Collection $dailies, Carbon $startDate, Carbon $endDate): array
    {
        // فلترة المصروفات حسب التاريخ
        $totalExpense = $dailies->sum(function($daily) use ($startDate, $endDate) {
            return $daily->expenses
                // ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('price');
        });

        // فلترة الطلبات حسب التاريخ
        $totalOrders = $dailies->sum(function($daily) use ($startDate, $endDate) {
            return $daily->orders
                // ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('price');
        });

        // فلترة الجلسات حسب التاريخ
        $totalSessions = $dailies->sum(function($daily) use ($startDate, $endDate) {
            return $daily->sessions
                // ->whereBetween('created_at', [$startDate, $endDate])
                ->sum(function($session) {
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
     * المنتجات الأكثر طلباً مع فلترة التاريخ
     */
    private function getMostRequestedProducts(Collection $dailies, Carbon $startDate, Carbon $endDate): Collection
    {
        return $dailies
            ->flatMap(function($daily) use ($startDate, $endDate) {
                return $daily->orders->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->flatMap(fn($order) => $order->items ?? [])
            ->filter(fn($item) => $item->product)
            ->groupBy(fn($item) => $item->product->name)
            ->map(fn($items, $productName) => [
                'productPath' => $items->first()->product->media?->path
                    ?? Media::where('category', 'products')->first()?->path
                    ?? '',
                'productName' => $productName,
                'totalOrders' => $items->count(),
                'totalQuantity' => $items->sum('quantity'),
            ])
            ->sortByDesc('totalOrders')
            ->values();
    }

    /**
     * الأجهزة الأكثر استخداماً مع فلترة التاريخ
     */
    private function getMostUsedDevices(Collection $dailies, Carbon $startDate, Carbon $endDate): Collection
    {
        return $dailies
            ->flatMap(function($daily) use ($startDate, $endDate) {
                return $daily->sessions?? collect([]);
                // ?->whereBetween('created_at', [$startDate, $endDate]) ?? collect([]);
            })
            ->flatMap(fn($session) => $session->bookedDevices ?? [])
            ->filter(fn($bookedDevice) => $bookedDevice?->device)
            ->groupBy(fn($bookedDevice) => $bookedDevice?->device->name)
            ->map(fn($group, $deviceName) => [
                'devicePath' => $group->first()?->device->media?->path,
                'deviceType' => $group->first()?->device->deviceType?->name,
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
