# 🗄️ استعلامات SQL مفيدة لـ LogBatch

## نظرة عامة

مجموعة من استعلامات SQL المفيدة للتعامل مع Activity Log و LogBatch.

---

## 📊 استعلامات أساسية

### 1. عرض جميع الـ Batches
```sql
SELECT 
    batch_uuid,
    MIN(created_at) as started_at,
    MAX(created_at) as ended_at,
    COUNT(*) as activities_count,
    GROUP_CONCAT(DISTINCT log_name) as models_affected
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY batch_uuid
ORDER BY started_at DESC
LIMIT 20;
```

### 2. عرض تفاصيل Batch معين
```sql
SELECT 
    id,
    log_name,
    event,
    description,
    subject_type,
    subject_id,
    causer_id,
    daily_id,
    created_at
FROM activity_log
WHERE batch_uuid = '9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b'
ORDER BY created_at;
```

### 3. عرض آخر 10 Batches
```sql
SELECT 
    batch_uuid,
    MIN(created_at) as started_at,
    COUNT(*) as activities_count
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY batch_uuid
ORDER BY started_at DESC
LIMIT 10;
```

---

## 🔍 استعلامات متقدمة

### 4. أكبر Batch (أكثر عدد من الأنشطة)
```sql
SELECT 
    batch_uuid,
    COUNT(*) as activities_count,
    MIN(created_at) as started_at,
    MAX(created_at) as ended_at,
    TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as duration_seconds
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY batch_uuid
ORDER BY activities_count DESC
LIMIT 10;
```

### 5. Batches حسب نوع الـ Model
```sql
SELECT 
    log_name,
    COUNT(DISTINCT batch_uuid) as batches_count,
    COUNT(*) as total_activities,
    AVG(activities_per_batch) as avg_activities_per_batch
FROM (
    SELECT 
        log_name,
        batch_uuid,
        COUNT(*) as activities_per_batch
    FROM activity_log
    WHERE batch_uuid IS NOT NULL
    GROUP BY log_name, batch_uuid
) as subquery
GROUP BY log_name
ORDER BY batches_count DESC;
```

### 6. Batches حسب نوع الحدث (Event)
```sql
SELECT 
    event,
    COUNT(DISTINCT batch_uuid) as batches_count,
    COUNT(*) as total_activities
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY event
ORDER BY batches_count DESC;
```

---

## 👤 استعلامات خاصة بالمستخدمين

### 7. Batches لمستخدم معين
```sql
SELECT 
    a.batch_uuid,
    u.name as user_name,
    MIN(a.created_at) as started_at,
    COUNT(*) as activities_count,
    GROUP_CONCAT(DISTINCT a.log_name) as models_affected
FROM activity_log a
LEFT JOIN users u ON a.causer_id = u.id
WHERE a.batch_uuid IS NOT NULL
  AND a.causer_id = 1
GROUP BY a.batch_uuid, u.name
ORDER BY started_at DESC;
```

### 8. أكثر المستخدمين نشاطاً (Batches)
```sql
SELECT 
    u.id,
    u.name,
    COUNT(DISTINCT a.batch_uuid) as batches_count,
    COUNT(*) as total_activities
FROM activity_log a
LEFT JOIN users u ON a.causer_id = u.id
WHERE a.batch_uuid IS NOT NULL
GROUP BY u.id, u.name
ORDER BY batches_count DESC
LIMIT 10;
```

---

## 📅 استعلامات خاصة بـ Daily

### 9. Batches لـ Daily معين
```sql
SELECT 
    batch_uuid,
    MIN(created_at) as started_at,
    COUNT(*) as activities_count,
    GROUP_CONCAT(DISTINCT log_name) as models_affected
FROM activity_log
WHERE batch_uuid IS NOT NULL
  AND daily_id = 5
GROUP BY batch_uuid
ORDER BY started_at DESC;
```

### 10. إحصائيات Batches لكل Daily
```sql
SELECT 
    daily_id,
    COUNT(DISTINCT batch_uuid) as batches_count,
    COUNT(*) as total_activities,
    AVG(activities_per_batch) as avg_activities_per_batch
FROM (
    SELECT 
        daily_id,
        batch_uuid,
        COUNT(*) as activities_per_batch
    FROM activity_log
    WHERE batch_uuid IS NOT NULL
      AND daily_id IS NOT NULL
    GROUP BY daily_id, batch_uuid
) as subquery
GROUP BY daily_id
ORDER BY daily_id DESC;
```

---

## 🛒 استعلامات خاصة بـ Orders

### 11. جميع Batches لـ Order معين
```sql
SELECT 
    a.batch_uuid,
    MIN(a.created_at) as started_at,
    COUNT(*) as activities_count,
    GROUP_CONCAT(DISTINCT a.log_name ORDER BY a.created_at) as operations_sequence
FROM activity_log a
WHERE a.batch_uuid IS NOT NULL
  AND (
    (a.subject_type = 'App\\Models\\Order\\Order' AND a.subject_id = 45)
    OR 
    a.batch_uuid IN (
        SELECT DISTINCT batch_uuid 
        FROM activity_log 
        WHERE subject_type = 'App\\Models\\Order\\Order' 
          AND subject_id = 45
    )
  )
GROUP BY a.batch_uuid
ORDER BY started_at DESC;
```

### 12. تفاصيل إنشاء Order (Batch الأول)
```sql
SELECT 
    id,
    log_name,
    event,
    description,
    subject_id,
    properties,
    created_at
FROM activity_log
WHERE batch_uuid = (
    SELECT batch_uuid
    FROM activity_log
    WHERE subject_type = 'App\\Models\\Order\\Order'
      AND subject_id = 45
      AND event = 'created'
    LIMIT 1
)
ORDER BY created_at;
```

---

## 📈 استعلامات إحصائية

### 13. إحصائيات عامة
```sql
SELECT 
    COUNT(DISTINCT batch_uuid) as total_batches,
    COUNT(*) as total_activities,
    COUNT(*) / COUNT(DISTINCT batch_uuid) as avg_activities_per_batch,
    MIN(created_at) as first_batch_date,
    MAX(created_at) as last_batch_date
FROM activity_log
WHERE batch_uuid IS NOT NULL;
```

### 14. Batches حسب التاريخ
```sql
SELECT 
    DATE(MIN(created_at)) as batch_date,
    COUNT(DISTINCT batch_uuid) as batches_count,
    COUNT(*) as total_activities
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY DATE(MIN(created_at))
ORDER BY batch_date DESC;
```

### 15. متوسط مدة الـ Batch
```sql
SELECT 
    AVG(duration_seconds) as avg_duration_seconds,
    MIN(duration_seconds) as min_duration_seconds,
    MAX(duration_seconds) as max_duration_seconds
FROM (
    SELECT 
        batch_uuid,
        TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as duration_seconds
    FROM activity_log
    WHERE batch_uuid IS NOT NULL
    GROUP BY batch_uuid
) as subquery;
```

---

## 🔎 استعلامات للتحليل

### 16. Batches التي تحتوي على أخطاء (إذا كان هناك logging للأخطاء)
```sql
SELECT 
    batch_uuid,
    MIN(created_at) as started_at,
    COUNT(*) as activities_count
FROM activity_log
WHERE batch_uuid IS NOT NULL
  AND properties LIKE '%error%'
GROUP BY batch_uuid
ORDER BY started_at DESC;
```

### 17. Batches غير المكتملة (عدد قليل من الأنشطة)
```sql
SELECT 
    batch_uuid,
    MIN(created_at) as started_at,
    COUNT(*) as activities_count
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY batch_uuid
HAVING COUNT(*) < 3
ORDER BY started_at DESC;
```

### 18. Batches الطويلة (أكثر من 10 ثواني)
```sql
SELECT 
    batch_uuid,
    MIN(created_at) as started_at,
    MAX(created_at) as ended_at,
    TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as duration_seconds,
    COUNT(*) as activities_count
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY batch_uuid
HAVING TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) > 10
ORDER BY duration_seconds DESC;
```

---

## 🧹 استعلامات الصيانة

### 19. حذف Batches القديمة (أكثر من 6 أشهر)
```sql
-- ⚠️ احذر: هذا سيحذف البيانات نهائياً
DELETE FROM activity_log
WHERE batch_uuid IN (
    SELECT batch_uuid
    FROM (
        SELECT batch_uuid
        FROM activity_log
        WHERE batch_uuid IS NOT NULL
          AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY batch_uuid
    ) as old_batches
);
```

### 20. عدد الأنشطة بدون Batch
```sql
SELECT 
    COUNT(*) as activities_without_batch,
    COUNT(*) * 100.0 / (SELECT COUNT(*) FROM activity_log) as percentage
FROM activity_log
WHERE batch_uuid IS NULL;
```

---

## 📊 Views مفيدة

### إنشاء View لـ Batch Summary
```sql
CREATE VIEW batch_summary AS
SELECT 
    batch_uuid,
    MIN(created_at) as started_at,
    MAX(created_at) as ended_at,
    TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as duration_seconds,
    COUNT(*) as activities_count,
    COUNT(DISTINCT log_name) as models_count,
    COUNT(DISTINCT subject_id) as subjects_count,
    GROUP_CONCAT(DISTINCT log_name) as models_affected,
    MIN(causer_id) as user_id,
    MIN(daily_id) as daily_id
FROM activity_log
WHERE batch_uuid IS NOT NULL
GROUP BY batch_uuid;
```

### استخدام الـ View
```sql
SELECT * FROM batch_summary
WHERE daily_id = 5
ORDER BY started_at DESC;
```

---

## 💡 نصائح

1. **استخدم Indexes** على `batch_uuid` لتحسين الأداء:
   ```sql
   CREATE INDEX idx_batch_uuid ON activity_log(batch_uuid);
   ```

2. **استخدم EXPLAIN** لفهم أداء الاستعلامات:
   ```sql
   EXPLAIN SELECT * FROM activity_log WHERE batch_uuid = '...';
   ```

3. **احفظ الاستعلامات المفيدة** في ملف أو أداة إدارة قاعدة البيانات

4. **راقب حجم الجدول** وقم بأرشفة البيانات القديمة بانتظام

---

## ✅ الخلاصة

هذه الاستعلامات تساعدك على:
- ✅ تحليل الأنشطة المجمعة
- ✅ مراقبة الأداء
- ✅ اكتشاف المشاكل
- ✅ إنشاء تقارير مفصلة
