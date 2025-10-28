<?php

namespace App\Filters\Daily;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Enums\Order\OrderTypeEnum;
class DailySearchFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        $query->where(function ($q) use ($value) {
            // البحث في الـ Daily نفسه
            $q->where('id', 'like', "%{$value}%")
              ->orWhere('start_date_time', 'like', "%{$value}%")
              ->orWhere('end_date_time', 'like', "%{$value}%")
              
              // البحث في Sessions
              ->orWhereHas('sessions', function ($sessionQuery) use ($value) {
                  $sessionQuery->where('id', 'like', "%{$value}%")
                      ->orWhere('name', 'like', "%{$value}%")
                      ->orWhereHas('bookedDevices.device', function($bookedQuery) use ($value) {
                          $bookedQuery->where('name', 'like', "%{$value}%");
                      });
              })
              
              // البحث في Orders
              ->orWhereHas('orders', function ($orderQuery) use ($value) {
                  $orderQuery->where('id', 'like', "%{$value}%")
                      ->orWhere('name', 'like', "%{$value}%")
                      ->orWhere('price', 'like', "%{$value}%");
              })
              
              // البحث في Expenses
              ->orWhereHas('expenses', function ($expenseQuery) use ($value) {
                  $expenseQuery->where('id', 'like', "%{$value}%")
                      ->orWhere('price', 'like', "%{$value}%")
                      ->orWhere('name', 'like', "%{$value}%");
              });
        });
    }
}