<?php
namespace App\Filters\Order;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
class FilterOrder implements Filter {
public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            $query->where('name', 'like', '%' . $value . '%')
            ->orWhere('price',$value)
            ->orWhere('number',$value);
        });
    }
}
