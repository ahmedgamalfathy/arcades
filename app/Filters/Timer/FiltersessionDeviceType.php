<?php

namespace App\Filters\Timer;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FiltersessionDeviceType implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            $query->orWhereHas('sessionDevice', function ($q) use ($value) {
                $q->where('type', $value);
            });
        });
    }
}
