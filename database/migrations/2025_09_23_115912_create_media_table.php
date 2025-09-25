<?php

use App\Enums\Media\MediaTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {// path , type , category
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->tinyInteger('type')->default(MediaTypeEnum::PHOTO->value);
            $table->string('category')->nullable();//avatar , device
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
