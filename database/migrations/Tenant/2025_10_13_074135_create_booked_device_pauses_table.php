<?php

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
        Schema::create('booked_device_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booked_device_id')->constrained('booked_devices')->cascadeOnDelete();
            $table->dateTime('paused_at');
            $table->dateTime('resumed_at')->nullable();
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booked_device_pauses');
    }
};
