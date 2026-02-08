<?php

use App\Models\Device\Device;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Device\DeviceType\DeviceType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {//name , rate, device_type_id,
        Schema::create('device_times', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate',8 , 2);
            $table->foreignIdFor(DeviceType::class)->nullable()->constrained();
            $table->foreignIdFor(Device::class)->nullable()->constrained();
            $table->unique(['name', 'device_type_id']);
            $table->unique(['name', 'device_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_times');
    }
};
;
