<?php

namespace App\Models\Expense;

use App\Models\User;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
class Expense extends Model
{
    use SoftDeletes ,UsesTenantConnection ,LogsActivity;
    protected $guarded = ['id'];
    protected $casts = [ 'date' => 'datetime'];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Expense')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Expense {$eventName}");
    }
    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->daily_id;
    }
}
