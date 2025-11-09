<?php
namespace App\Filters\Timer;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\BookedDevice\BookedDeviceEnum;
use Carbon\Carbon;
use App\Models\Setting\Param\Param;

class FilterTypeSessionDevice implements Filter 
{
    public function __invoke(Builder $query, $value, string $property)
    {
        $now = Carbon::now();
        $param = Param::on('tenant')
            ->where('parameter_order', 1)
            ->first();
        $defaultTimeNotification = (int) $param->type;

        return $query->whereHas('bookedDevices', function ($q) use ($value, $now, $defaultTimeNotification) {
            
            if ($value === '1') {
                $q->where('status', '!=', BookedDeviceEnum::FINISHED->value)
                  ->where('end_date_time', '<=', $now);
                  
            } elseif ($value === '2') {
                $q->where('status', '!=', BookedDeviceEnum::FINISHED->value)
                  ->whereBetween('end_date_time', [
                      $now->copy()->addMinutes($defaultTimeNotification),
                      $now->copy()->addMinutes($defaultTimeNotification + 1)
                  ]);
                  
            } else {
                $q->where('status', '!=', BookedDeviceEnum::FINISHED->value)
                ->whereIn('status', [BookedDeviceEnum::PAUSED->value, BookedDeviceEnum::RESUME->value,BookedDeviceEnum::ACTIVE->value]);
            }
        });
    }
}