<?php

namespace App\Filters\User;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class FilterUserRole implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        $roles = is_array($value) ? $value : explode(',', $value);
        $query->whereHas('roles', function ($query) use ($roles) {
            $query->whereIn('id', $roles);
        });

        return $query;
    }
}
