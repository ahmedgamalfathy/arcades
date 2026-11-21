<?php

namespace App\Models\Media;

use App\Enums\Media\MediaTypeEnum;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use UsesTenantConnection;

    protected $guarded = [];

    protected $casts = [
        'type' => MediaTypeEnum::class,
    ];

    protected function path(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Storage::disk('public')->url($value) : '',
        );
    }
}
