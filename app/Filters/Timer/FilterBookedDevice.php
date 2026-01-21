<?php
namespace App\Filters\Timer;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
class FilterBookedDevice implements Filter {
public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
                $query->orWhereHas('device', function ($q) use ($value) {
                  $q->where('name', 'like', '%' . $value . '%');
                });
        });
    }
}
