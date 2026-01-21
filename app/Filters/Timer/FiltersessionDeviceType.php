<?php
namespace App\Filters\Timer;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
class FiltersessionDeviceType implements Filter {
public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
                $query->orWhereHas('sessionDevice', function ($q) use ($value) {
                  $q->where('type', $value);
                });
        });
    }
}
