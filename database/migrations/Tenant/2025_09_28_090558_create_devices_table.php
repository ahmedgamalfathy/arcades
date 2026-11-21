<?php

use App\Enums\Device\DeviceStatusEnum;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Media\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {// name , status , device_type_id ,media_id
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->tinyInteger('status')->default(DeviceStatusEnum::AVAILABLE->value);
            $table->foreignIdFor(DeviceType::class)->constrained();
            $table->foreignIdFor(Media::class)->nullable()->constrained();
            $table->unique(['name', 'device_type_id']);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
