<?php

namespace App\Filters\Timer;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FilterBookedDevice implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            $query->orWhereHas('device', function ($q) use ($value) {
                $q->where('name', 'like', '%'.$value.'%');
            });
        });
    }
}
