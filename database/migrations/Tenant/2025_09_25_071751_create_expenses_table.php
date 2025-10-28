<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Models\User;
use App\Models\Daily\Daily;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();//name , price , date , note ,type
            // $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->tinyInteger('type')->default(ExpenseTypeEnum::EXTERNAL->value);
            $table->decimal('price', 8,2);
            $table->date('date')->default(Carbon::now());
            $table->text('note')->nullable();
            $table->foreignIdFor(Daily::class)->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
