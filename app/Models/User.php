<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Casts\EncryptedTenantPassword;
use App\Enums\StatusEnum;
use App\Models\Expense\Expense;
use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory , HasRoles ,Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'email',
        'media_id',
        'is_active',
        'email_verified_at',
        'password',
        'code',
        'expired_at',
        'user_id',
        // Tenant connection fields must only be assigned by trusted service/seed code.
        // Never pass unfiltered request payloads directly into User::create() or update().
        'database_name',
        'database_username',
        'database_password',
        'app_key',
        'remember_token',
    ];

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
        'database_password',
        'remember_token',
        'code',
        'expired_at',
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
            'expired_at' => 'datetime',
            'password' => 'hashed',
            'database_password' => EncryptedTenantPassword::class,
            'is_active' => StatusEnum::class,
        ];
    }

    public function scopeManagedBy(Builder $query, User $actor): Builder
    {
        $accountOwnerId = $actor->user_id ?? $actor->id;

        return $query->where('user_id', $accountOwnerId);
    }

    public function media()
    {
        return $this->setConnection('tenant')->belongsTo(Media::class, 'media_id');
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
