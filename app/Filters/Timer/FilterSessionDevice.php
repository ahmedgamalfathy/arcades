<?php

namespace App\Filters\Timer;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FilterSessionDevice implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            $query->where('name', 'like', '%'.$value.'%')
                ->orWhereHas('bookedDevices.device', function ($q) use ($value) {
                    $q->where('name', 'like', '%'.$value.'%');
                });
        });
    }
}
