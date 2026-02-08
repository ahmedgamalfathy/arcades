<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StatusEnum;
use App\Models\Expense\Expense;
use App\Models\Media\Media;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable , HasRoles ,HasApiTokens, SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
     protected $connection = 'mysql';
     protected $guarded =[];
    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()
    //         ->useLogName('User')
    //         ->logAll()
    //         ->logOnlyDirty()
    //         ->dontLogIfAttributesChangedOnly(['updated_at'])
    //         ->setDescriptionForEvent(fn(string $eventName) => "User {$eventName}");
    // }
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => StatusEnum::class
        ];
    }

    public function media()
    {
       return  $this->setConnection('tenant')->belongsTo(Media::class,'media_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
    public function getAvatarPathAttribute(): string
    {
        return Media::on('tenant')
            ->where('id', $this->media_id)
            ->value('path') ?? '';
    }

}
