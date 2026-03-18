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
        $tableName = 'products';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('products_name_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique(['name', 'deleted_at'], 'products_name_deleted_at_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'products';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('products_name_deleted_at_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique('name', 'products_name_unique');
        });
    }
};
