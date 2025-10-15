<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Enums\SessionDevice\SessionDeviceEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {//name , type
        Schema::create('session_devices', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->default(SessionDeviceEnum::INDIVIDUAL->value);
            $table->string('name');
            // $table->foreignId('daily_id')->nullable()->constrained('dailies')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_devices');
    }
};
