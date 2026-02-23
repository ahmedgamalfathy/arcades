<?php

namespace App\Http\Controllers\API\V1\Dashboard\ActivityLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\BatchedActivityResource;
use App\Helpers\ApiResponse;

class BatchedActivityController extends Controller
{
    /**
     * عرض جميع الـ batches مع ملخص لكل batch
     */
    public function index(Request $request)
    {
        $perPage = $request->query('perPage', 15);
        $dailyId = $request->query('dailyId');

        // الحصول على جميع batch_uuids الفريدة
        $query = Activity::selectRaw('
                batch_uuid,
                MIN(id) as first_activity_id,
                MIN(created_at) as started_at,
                MAX(created_at) as ended_at,
                COUNT(*) as activities_count
            ')
            ->whereNotNull('batch_uuid')
            ->groupBy('batch_uuid')
            ->orderByDesc('started_at');

        // تصفية حسب daily_id إذا تم تحديده
        if ($dailyId) {
            $query->where('daily_id', $dailyId);
        }

        $batches = $query->paginate($perPage);

        // تحميل الأنشطة لكل batch
        $batchesWithActivities = $batches->getCollection()->map(function ($batch) {
            $activities = Activity::where('batch_uuid', $batch->batch_uuid)
                ->orderBy('created_at')
                ->get();

            return $activities;
        });

        return ApiResponse::success(
            BatchedActivityResource::collection($batchesWithActivities),
            'Batched activities retrieved successfully',
            [
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'per_page' => $batches->perPage(),
                    'total' => $batches->total(),
                    'last_page' => $batches->lastPage(),
                ]
            ]
        );
    }

    /**
     * عرض تفاصيل batch معين
     */
    public function show(string $batchUuid)
    {
        $activities = Activity::where('batch_uuid', $batchUuid)
            ->orderBy('created_at')
            ->get();

        if ($activities->isEmpty()) {
            return ApiResponse::error('Batch not found', 404);
        }

        return ApiResponse::success(
            new BatchedActivityResource($activities),
            'Batch details retrieved successfully'
        );
    }

    /**
     * عرض الـ batches الخاصة بـ Order معين
     */
    public function orderBatches(int $orderId)
    {
        $batches = Activity::selectRaw('
                batch_uuid,
                MIN(created_at) as started_at,
                MAX(created_at) as ended_at,
                COUNT(*) as activities_count
            ')
            ->whereNotNull('batch_uuid')
            ->where('subject_type', 'App\\Models\\Order\\Order')
            ->where('subject_id', $orderId)
            ->groupBy('batch_uuid')
            ->orderByDesc('started_at')
            ->get();

        $batchesWithActivities = $batches->map(function ($batch) {
            return Activity::where('batch_uuid', $batch->batch_uuid)
                ->orderBy('created_at')
                ->get();
        });

        return ApiResponse::success(
            BatchedActivityResource::collection($batchesWithActivities),
            'Order batches retrieved successfully'
        );
    }

    /**
     * عرض الـ batches الخاصة بـ Daily معين
     */
    public function dailyBatches(int $dailyId)
    {
        $batches = Activity::selectRaw('
                batch_uuid,
                MIN(created_at) as started_at,
                MAX(created_at) as ended_at,
                COUNT(*) as activities_count
            ')
            ->whereNotNull('batch_uuid')
            ->where('daily_id', $dailyId)
            ->groupBy('batch_uuid')
            ->orderByDesc('started_at')
            ->get();

        $batchesWithActivities = $batches->map(function ($batch) {
            return Activity::where('batch_uuid', $batch->batch_uuid)
                ->orderBy('created_at')
                ->get();
        });

        return ApiResponse::success(
            BatchedActivityResource::collection($batchesWithActivities),
            'Daily batches retrieved successfully'
        );
    }

    /**
     * إحصائيات الـ batches
     */
    public function statistics(Request $request)
    {
        $dailyId = $request->query('dailyId');

        $query = Activity::whereNotNull('batch_uuid');

        if ($dailyId) {
            $query->where('daily_id', $dailyId);
        }

        $stats = [
            'totalBatches' => $query->distinct('batch_uuid')->count('batch_uuid'),
            'totalActivities' => $query->count(),
            'averageActivitiesPerBatch' => round(
                $query->count() / max($query->distinct('batch_uuid')->count('batch_uuid'), 1),
                2
            ),
            'batchesByLogName' => Activity::selectRaw('log_name, COUNT(DISTINCT batch_uuid) as batches_count')
                ->whereNotNull('batch_uuid')
                ->when($dailyId, fn($q) => $q->where('daily_id', $dailyId))
                ->groupBy('log_name')
                ->get()
                ->pluck('batches_count', 'log_name'),
            'batchesByEvent' => Activity::selectRaw('event, COUNT(DISTINCT batch_uuid) as batches_count')
                ->whereNotNull('batch_uuid')
                ->when($dailyId, fn($q) => $q->where('daily_id', $dailyId))
                ->groupBy('event')
                ->get()
                ->pluck('batches_count', 'event'),
        ];

        return ApiResponse::success($stats, 'Batch statistics retrieved successfully');
    }
}
