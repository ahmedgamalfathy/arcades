<?php

namespace App\Http\Resources\ActivityLog\Test;

use App\Http\Resources\ActivityLog\Test\Concerns\ResolvesActivityChildren;
use App\Http\Resources\ActivityLog\Test\Concerns\ResolvesActivityModels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AllDailyActivityResource extends JsonResource
{
    use ResolvesActivityChildren;
    use ResolvesActivityModels;

    public function toArray(Request $request): array
    {
        $details = $this->resolveResource();

        // Add parent info to details if exists (for standalone children)
        if (!empty($this->parentInfo)) {
            $details['parentInfo'] = $this->parentInfo;
        }

        // Process all children - filtering is done in controller
        $children = [];
        if (!empty($this->children)) {
            foreach ($this->children as $child) {
                $children[] = $this->resolveChildDetails($child);
            }
        }

        return [
            'activityLogId' => $this->id,
            'date' => Carbon::parse($this->created_at)->format('d-M'),
            'time' => Carbon::parse($this->created_at)->format('H:i'),
            'eventType' => $this->event,
            'userName' => $this->causerName,
            'model' => [
                'modelName' => $this->log_name,
                'modelId' => $this->subject_id,
            ],
            'details' => $details,
            'children' => $children,
        ];
    }

}
