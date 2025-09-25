<?php
namespace App\Filters\Product;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
class FilterProduct implements Filter {
public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            $query->where('name', 'like', '%' . $value . '%')
            ->orWhere('price',$value);
        });
    }
}
