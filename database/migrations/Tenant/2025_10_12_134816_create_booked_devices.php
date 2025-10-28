<?php

use App\Enums\BookedDevice\BookedDeviceEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booked_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_device_id')->constrained('session_devices')->cascadeOnDelete();
            $table->foreignId('device_type_id')->constrained('device_types')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('device_time_id')->constrained('device_times')->cascadeOnDelete();

            $table->dateTime('start_date_time');
            $table->dateTime('end_date_time')->nullable();

            $table->tinyInteger('status')->default(BookedDeviceEnum::FINISHED->value);//'active', 'paused', 'finished'


            $table->unsignedBigInteger('total_paused_seconds')->default(0);
            $table->unsignedBigInteger('total_used_seconds')->default(0);
            $table->integer('end_date_static')->nullable();

            $table->decimal('period_cost',10,2)->default(0);
            $table->boolean('is_notification_sent')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booked_devices');
    }
};
