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

        // 2. إضافة indexes لتحسين الأداء باستخدام raw SQL
        $database = DB::connection($connection)->getDatabaseName();
        
        // Helper function للتحقق من وجود index
        $indexExists = function($indexName) use ($connection, $database, $tableName) {
            $result = DB::connection($connection)->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$database, $tableName, $indexName]
            );
            return $result[0]->count > 0;
        };

        // Index على batch_uuid للاستعلامات السريعة
        if (!$indexExists('idx_batch_uuid')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` ADD INDEX `idx_batch_uuid` (`batch_uuid`)");
        }

        // Index على daily_id للتصفية حسب اليوم
        if (!$indexExists('idx_daily_id')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` ADD INDEX `idx_daily_id` (`daily_id`)");
        }

        // Index على causer_id للتصفية حسب المستخدم
        if (!$indexExists('idx_causer_id')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` ADD INDEX `idx_causer_id` (`causer_id`)");
        }

        // Index على created_at للترتيب الزمني
        if (!$indexExists('idx_created_at')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` ADD INDEX `idx_created_at` (`created_at`)");
        }

        // Composite index للاستعلامات المعقدة
        if (!$indexExists('idx_batch_created')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` ADD INDEX `idx_batch_created` (`batch_uuid`, `created_at`)");
        }
        
        if (!$indexExists('idx_daily_batch')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` ADD INDEX `idx_daily_batch` (`daily_id`, `batch_uuid`)");
        }
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
        $database = DB::connection($connection)->getDatabaseName();

        // Helper function للتحقق من وجود index
        $indexExists = function($indexName) use ($connection, $database, $tableName) {
            $result = DB::connection($connection)->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$database, $tableName, $indexName]
            );
            return $result[0]->count > 0;
        };

        if ($indexExists('idx_batch_uuid')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` DROP INDEX `idx_batch_uuid`");
        }
        if ($indexExists('idx_daily_id')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` DROP INDEX `idx_daily_id`");
        }
        if ($indexExists('idx_causer_id')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` DROP INDEX `idx_causer_id`");
        }
        if ($indexExists('idx_created_at')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` DROP INDEX `idx_created_at`");
        }
        if ($indexExists('idx_batch_created')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` DROP INDEX `idx_batch_created`");
        }
        if ($indexExists('idx_daily_batch')) {
            DB::connection($connection)->statement("ALTER TABLE `{$tableName}` DROP INDEX `idx_daily_batch`");
        }
    }
}
