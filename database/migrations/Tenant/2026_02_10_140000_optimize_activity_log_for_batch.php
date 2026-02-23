<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class OptimizeActivityLogForBatch extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // استخدام اتصال tenant مباشرة
        $connection = 'tenant';
        $tableName = 'activity_log';

        // 1. حذف جميع البيانات القديمة
        DB::connection($connection)->table($tableName)->truncate();

        // 2. إضافة indexes لتحسين الأداء
        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            // Index على batch_uuid للاستعلامات السريعة
            $table->index('batch_uuid', 'idx_batch_uuid');

            // Index على daily_id للتصفية حسب اليوم
            $table->index('daily_id', 'idx_daily_id');

            // Index على causer_id للتصفية حسب المستخدم
            $table->index('causer_id', 'idx_causer_id');

            // Index على created_at للترتيب الزمني
            $table->index('created_at', 'idx_created_at');

            // Composite index للاستعلامات المعقدة
            $table->index(['batch_uuid', 'created_at'], 'idx_batch_created');
            $table->index(['daily_id', 'batch_uuid'], 'idx_daily_batch');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // استخدام اتصال tenant مباشرة
        $connection = 'tenant';
        $tableName = 'activity_log';

        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            $table->dropIndex('idx_batch_uuid');
            $table->dropIndex('idx_daily_id');
            $table->dropIndex('idx_causer_id');
            $table->dropIndex('idx_created_at');
            $table->dropIndex('idx_batch_created');
            $table->dropIndex('idx_daily_batch');
        });
    }
}
