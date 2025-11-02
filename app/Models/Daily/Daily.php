<?php

namespace App\Models\Daily;

use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Expense\Expense;
use App\Models\Order\Order;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Enums\Order\OrderTypeEnum;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class Daily extends Model
{
    use UsesTenantConnection , LogsActivity ;

    protected $table = 'dailies';
    protected $guarded = [];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Daily')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Daily {$eventName}");
    }
    public function sessions()
    {
        return $this->hasMany(SessionDevice::class);
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class)->where('type',ExpenseTypeEnum::INTERNAL->value);
    }
    public function orders()
    {
        return $this->hasMany(Order::class)->where('type',OrderTypeEnum::INTERNAL->value);
    }
    public function totalExpenses()
    {
        return $this->expenses()
            ->where('type', ExpenseTypeEnum::INTERNAL->value)
            ->sum('price');
    }

    public function totalOrders()
    {
        return $this->orders()
            ->where('type', OrderTypeEnum::INTERNAL->value)
            ->sum('price');
    }
    public function totalSessionDevices()
    {
        return $this->sessions->sum(function($session) {
            return $session->bookedDevices->sum('period_cost');
        });
    }
    public function totalProfit()
    {
        return ($this->totalOrders() + $this->totalSessionDevices())- $this->totalExpenses();
    }
     public function scopeHasExpenses($query)
    {
        return $query->whereHas('expenses');
    }
     public function scopeHasSessions($query)
    {
        return $query->whereHas('sessions');
    }
        public function scopeHasOrders($query)
    {
        return $query->whereHas('orders');
    }
    public function getTotalOrdersAttribute()
    {
        return $this->orders()->sum('price');
    }
    public function getTotalSessionDevicesAttribute()
    {
        return $this->sessions->sum(function($session) {
            return $session->bookedDevices->sum('period_cost');
        });
    } 
    public function getTotalExpensesAttribute()
    {
        return $this->expenses()->sum('price');
    }
     public function getTotalProfitAttribute()
    {
        return ($this->total_orders + $this->total_session_devices) - $this->total_expenses;
    }
   
}
