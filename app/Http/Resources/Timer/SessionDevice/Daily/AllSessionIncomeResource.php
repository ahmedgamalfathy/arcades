<?php

namespace App\Http\Resources\Timer\SessionDevice\Daily;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Enums\BookedDevice\BookedDeviceEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Timer\BookedDevice\BookedDevcieResource;

class AllSessionIncomeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
            // حساب التكلفة الإجمالية (آخر سجل فقط لكل جهاز)
        $totalSessionCost = $this->calculateSessionTotalCost();
        // اسم الجلسة
        $sessionName = $this->getSessionDisplayName();
        return [
            'sessionId'=>$this->id,
            'sessioName'=> $sessionName,
            'totalPriceSession'=>$totalSessionCost,
            'createdAt' =>Carbon::parse($this->created_at)->format('d/m/Y'),
            'timeCreated' =>Carbon::parse($this->created_at)->format('H:i'),
            'periodCost'=>$totalSessionCost,
            // 'periodCost'=>$this->bookedDevices->sum('actual_paid_amount')??"",
        ];
    }
     /**
     * حساب التكلفة الإجمالية للجلسة (آخر سجل فقط لكل جهاز)
     */
    private function calculateSessionTotalCost(): float
    {
        return $this->bookedDevices
            ->where('status', BookedDeviceEnum::FINISHED->value)
            ->groupBy('device_id')
            ->map(fn($devices) => $devices->sortByDesc('id')->first())
            ->sum(fn($device) => (float) ($device->actual_paid_amount ?? 0));
    }

    /**
     * الحصول على اسم الجلسة
     */
    private function getSessionDisplayName(): string
    {
        if ($this->name !== 'individual') {
            return $this->name;
        }
        $firstDevice = $this->bookedDevices->first();
        return $firstDevice?->device?->name ?? 'individualSession';
    }
}
