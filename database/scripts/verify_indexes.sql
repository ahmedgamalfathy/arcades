-- ============================================
-- التحقق من Indexes في activity_log
-- ============================================

-- استخدام قاعدة البيانات الصحيحة
USE arcade_1;

-- 1. عرض جميع Indexes
SHOW INDEXES FROM activity_log;

-- 2. عرض Indexes الجديدة فقط
SHOW INDEXES FROM activity_log WHERE Key_name LIKE 'idx_%';

-- 3. عرض بنية الجدول
DESCRIBE activity_log;

-- 4. عرض عدد السجلات
SELECT COUNT(*) as total_records FROM activity_log;

-- 5. عرض CREATE TABLE statement
SHOW CREATE TABLE activity_log;
