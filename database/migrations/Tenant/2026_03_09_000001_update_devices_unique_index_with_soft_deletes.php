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
        $tableName = 'devices';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('devices_name_device_type_id_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique(['name', 'device_type_id', 'deleted_at'], 'devices_name_device_type_id_deleted_at_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'devices';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('devices_name_device_type_id_deleted_at_unique');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique(['name', 'device_type_id'], 'devices_name_device_type_id_unique');
        });
    }
};
