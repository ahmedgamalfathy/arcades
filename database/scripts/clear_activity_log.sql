-- ============================================
-- Script لحذف بيانات activity_log القديمة
-- ============================================

-- 1. عرض عدد السجلات الحالية
SELECT
    COUNT(*) as total_records,
    COUNT(DISTINCT batch_uuid) as batches_with_uuid,
    COUNT(*) - COUNT(batch_uuid) as records_without_uuid
FROM activity_log;

-- 2. عرض أقدم وأحدث سجل
SELECT
    MIN(created_at) as oldest_record,
    MAX(created_at) as newest_record,
    DATEDIFF(MAX(created_at), MIN(created_at)) as days_span
FROM activity_log;

-- 3. عرض توزيع السجلات حسب log_name
SELECT
    log_name,
    COUNT(*) as count,
    COUNT(batch_uuid) as with_batch_uuid
FROM activity_log
GROUP BY log_name
ORDER BY count DESC;

-- ============================================
-- Backup (اختياري)
-- ============================================

-- إنشاء جدول backup
CREATE TABLE IF NOT EXISTS activity_log_backup_20260210 AS
SELECT * FROM activity_log;

-- التحقق من الـ backup
SELECT COUNT(*) as backup_count FROM activity_log_backup_20260210;

-- ============================================
-- حذف البيانات
-- ============================================

-- خيار 1: حذف جميع البيانات
TRUNCATE TABLE activity_log;

-- خيار 2: حذف البيانات القديمة فقط (أكثر من 30 يوم)
-- DELETE FROM activity_log
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- خيار 3: حذف السجلات بدون batch_uuid فقط
-- DELETE FROM activity_log
-- WHERE batch_uuid IS NULL;

-- ============================================
-- التحقق بعد الحذف
-- ============================================

SELECT COUNT(*) as remaining_records FROM activity_log;

-- ============================================
-- استعادة من الـ Backup (إذا لزم الأمر)
-- ============================================

-- INSERT INTO activity_log
-- SELECT * FROM activity_log_backup_20260210;

-- ============================================
-- حذف جدول الـ Backup بعد التأكد
-- ============================================

-- DROP TABLE IF EXISTS activity_log_backup_20260210;
