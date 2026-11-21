<?php

namespace App\Filters\DeviceType;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FilterDeviceType implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        return $query->where(function ($query) use ($value) {
            // البحث في اسم نوع الجهاز نفسه
            $query->where('name', 'like', '%'.$value.'%')
                  // البحث كمان في الأجهزة المرتبطة
                ->orWhereHas('devices', function ($q) use ($value) {
                    $q->where('name', 'like', '%'.$value.'%');
                });
        });
    }
}
