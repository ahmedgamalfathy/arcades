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
        $tableName = 'device_times';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('device_times_name_device_type_id_unique');
            $table->dropUnique('device_times_name_device_id_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique(['name', 'device_type_id', 'deleted_at'], 'device_times_name_device_type_id_deleted_at_unique');
            $table->unique(['name', 'device_id', 'deleted_at'], 'device_times_name_device_id_deleted_at_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'device_times';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('device_times_name_device_type_id_deleted_at_unique');
            $table->dropUnique('device_times_name_device_id_deleted_at_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique(['name', 'device_type_id'], 'device_times_name_device_type_id_unique');
            $table->unique(['name', 'device_id'], 'device_times_name_device_id_unique');
        });
    }
};
