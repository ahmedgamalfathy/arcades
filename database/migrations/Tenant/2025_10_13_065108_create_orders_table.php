<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\OrderTypeEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Timer\BookedDevice\BookedDevice;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('name')->nullable()->unique();
            $table->tinyInteger('type')->default(OrderTypeEnum::EXTERNAL->value);
            $table->foreignIdFor(BookedDevice::class)->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
