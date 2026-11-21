<?php

namespace App\Filters\Order;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FilterOrder implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            $query->where('name', 'like', '%'.$value.'%')
                ->orWhere('price', $value)
                ->orWhere('number', 'like', '%'.$value.'%');
            $query->orWhereHas('items', function ($query) use ($value) {
                $query->whereHas('product', function ($query) use ($value) {
                    $query->where('name', 'like', '%'.$value.'%');
                });
            });

        });
    }
}
