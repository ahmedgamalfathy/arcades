<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Models\Expense\Expense;
use App\Models\User;
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
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->tinyInteger('type')->default(ExpenseTypeEnum::EXTERNAL->value);
            $table->decimal('price', 8,2);
            $table->date('date')->default(Carbon::now());
            $table->text('note')->nullable();
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
