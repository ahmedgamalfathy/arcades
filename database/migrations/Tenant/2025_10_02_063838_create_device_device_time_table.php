<?php

use App\Models\Device\Device;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Device\DeviceTime\DeviceTime;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_device_time', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(DeviceTime::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Device::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_device_time');
    }
};
