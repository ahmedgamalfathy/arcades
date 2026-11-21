<?php

namespace App\Filters\Timer;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Models\Setting\Param\Param;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FilterTypeBookedDeviceParam implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        $now = Carbon::now();

        $param = Param::on('tenant')
            ->where('parameter_order', 1)
            ->first();

        $defaultTimeNotification = (int) ($param->type ?? 0);

        $query->where(function ($q) use ($value, $now, $defaultTimeNotification) {

            if ($value === '1') {
                // انتهى وقته
                $q->where('status', '!=', BookedDeviceEnum::FINISHED->value)
                    ->where('end_date_time', '<=', $now);

            } elseif ($value === '2') {
                // قرب ينتهي
                $startTime = $now->copy();
                $endTime = $now->copy()->addMinutes($defaultTimeNotification);
                $q->where('status', '!=', BookedDeviceEnum::FINISHED->value)
                    ->whereBetween('end_date_time', [$startTime, $endTime]);

            } else {
                // شغال / موقوف / مستأنف
                $q->whereIn('status', [
                    BookedDeviceEnum::PAUSED->value,
                    BookedDeviceEnum::RESUME->value,
                    BookedDeviceEnum::ACTIVE->value,
                ]);
            }
        });
    }
}
