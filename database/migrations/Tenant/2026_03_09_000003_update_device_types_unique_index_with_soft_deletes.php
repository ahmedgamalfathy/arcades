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
        $tableName = 'device_types';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('device_types_name_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique(['name', 'deleted_at'], 'device_types_name_deleted_at_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'device_types';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('device_types_name_deleted_at_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique('name', 'device_types_name_unique');
        });
    }
};