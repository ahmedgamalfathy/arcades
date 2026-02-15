<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource لعرض الأنشطة المجمعة (Batched Activities)
 * يستخدم لعرض مجموعة من الأنشطة المترابطة تحت batch_uuid واحد
 */
class BatchedActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $this->resource هو Collection من Activities لها نفس batch_uuid
        $activities = $this->resource;

        if ($activities->isEmpty()) {
            return [];
        }

        $firstActivity = $activities->first();
        $lastActivity = $activities->last();

        return [
            'batchUuid' => $firstActivity->batch_uuid,
            'userName' => $firstActivity->causer?->name ?? 'System',
            'userId' => $firstActivity->causer_id,
            'dailyId' => $firstActivity->daily_id,
            'startedAt' => $firstActivity->created_at?->format('Y-m-d H:i:s'),
            'endedAt' => $lastActivity->created_at?->format('Y-m-d H:i:s'),
            'activitiesCount' => $activities->count(),
            'summary' => $this->generateSummary($activities),
            'activities' => $this->formatActivities($activities),
        ];
    }

    /**
     * توليد ملخص للعمليات في الـ batch
     */
    private function generateSummary($activities): string
    {
        $events = $activities->groupBy('event');
        $summary = [];

        foreach ($events as $event => $items) {
            $count = $items->count();
            $logName = $items->first()->log_name;

            $eventArabic = match($event) {
                'created' => 'إنشاء',
                'updated' => 'تحديث',
                'deleted' => 'حذف',
                'restored' => 'استعادة',
                default => $event
            };

            $summary[] = "{$eventArabic} {$count} {$logName}";
        }

        return implode(' + ', $summary);
    }

    /**
     * تنسيق الأنشطة للعرض
     */
    private function formatActivities($activities): array
    {
        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'logName' => $activity->log_name,
                'event' => $activity->event,
                'description' => $activity->description,
                'subjectType' => class_basename($activity->subject_type),
                'subjectId' => $activity->subject_id,
                'properties' => $this->formatProperties($activity),
                'createdAt' => $activity->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    /**
     * تنسيق properties حسب نوع الحدث
     */
    private function formatProperties($activity): array
    {
        return match($activity->event) {
            'created' => [
                'type' => 'created',
                'data' => $activity->properties['attributes'] ?? []
            ],
            'updated' => [
                'type' => 'updated',
                'changes' => $this->getChanges($activity)
            ],
            'deleted' => [
                'type' => 'deleted',
                'data' => $activity->properties['old'] ?? []
            ],
            'restored' => [
                'type' => 'restored',
                'data' => $activity->properties['attributes'] ?? []
            ],
            default => $activity->properties
        };
    }

    /**
     * استخراج التغييرات من activity
     */
    private function getChanges($activity): array
    {
        $attributes = $activity->properties['attributes'] ?? [];
        $old = $activity->properties['old'] ?? [];

        $changes = [];

        foreach ($attributes as $key => $newValue) {
            if (array_key_exists($key, $old) && $old[$key] != $newValue) {
                $changes[$key] = [
                    'old' => $old[$key],
                    'new' => $newValue
                ];
            }
        }

        return $changes;
    }
}
