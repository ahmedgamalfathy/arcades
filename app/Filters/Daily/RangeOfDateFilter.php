<?php

namespace App\Filters\Daily;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class RangeOfDateFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        if (is_array($value)) {
            [$start, $end] = array_pad($value, 2, null);
        }
        // لو string "2026-02-01,2026-02-20"
        else {
            [$start, $end] = array_pad(explode(',', $value), 2, null);
        }

        if (!$start && !$end) {
            return;
        }

        $startDate = $start
            ? Carbon::parse($start)->startOfDay()
            : null;

        $endDate = $end
            ? Carbon::parse($end)->endOfDay()
            : null;

        if ($startDate && $endDate) {
            $query->whereBetween('start_date_time', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('start_date_time', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('start_date_time', '<=', $endDate);
        }

    }
}
