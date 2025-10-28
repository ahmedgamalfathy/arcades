<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Daily\Daily;
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
            $table->foreignIdFor(Daily::class)->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
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
