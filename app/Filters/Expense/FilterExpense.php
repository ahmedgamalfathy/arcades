<?php

namespace App\Filters\Expense;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FilterExpense implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            $query->where('name', 'like', '%'.$value.'%')
                ->orWhere('price', $value);
        });
    }
}
