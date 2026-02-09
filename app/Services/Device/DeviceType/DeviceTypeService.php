<?php
namespace App\Services\Device\DeviceType;

use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Filters\DeviceType\FilterDeviceType;
use App\Models\Device\DeviceType\DeviceType;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeviceTypeService
{
    public function allDeviceTypes(Request $request)
    {
        $perPage = $request->query('perPage', 10);
        $query = QueryBuilder::for(DeviceType::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterDeviceType()),
            ])
            ->with('devices');

        if ($request->has('trashed') && $request->trashed == 1) {
            $query->onlyTrashed();
        }

        return $query->cursorPaginate($perPage);
    }
    public function editDeviceType(int $id)
    {
        $deviceType = DeviceType::with('deviceTimes')->find($id);
        if(!$deviceType){
        throw new ModelNotFoundException("Device Type with id {$id} not found");
        }
        return  $deviceType;
    }
    public function createDeviceType(array $data)
    {
        $deviceType = DeviceType::create([
            'name'=>$data['name'],
        ]);
        return $deviceType;
    }
    public function updateDeviceType(int $id,array $data)
    {
        $deviceType = DeviceType::find($id);
        if(!$deviceType){
        throw new ModelNotFoundException("Device Type with id {$id} not found");
        }
        $deviceType->name = $data['name'];
        $deviceType->save();
        return $deviceType;
    }
    public function deleteDeviceType(int $id)
    {
        $deviceType = DeviceType::find($id);
        if (!$deviceType) {
            throw new ModelNotFoundException("Device Type with id {$id} not found");
        }

        // التحقق من إمكانية الحذف
        if (!$deviceType->canBeDeleted()) {
            throw ValidationException::withMessages([
                'deviceType'=>$deviceType->getDeletionBlockReason()
            ]);
        }

        // حذف الأجهزة غير النشطة أولاً (Soft Delete)
        $deviceType->devices()->each(function($device) {
            if (!$device->hasActiveBookings()) {
                $device->delete(); // Soft Delete
            }
        });

        // حذف نوع الجهاز (Soft Delete)
        $deviceType->delete();
        return $deviceType;
    }

    public function restoreDeviceType(int $id)
    {
        $deviceType = DeviceType::onlyTrashed()->find($id);
        if (!$deviceType) {
            throw new ModelNotFoundException("Device Type with id {$id} not found in trashed");
        }
        $deviceType->restore();
        return $deviceType;
    }

    public function forceDeleteDeviceType(int $id)
    {
        $deviceType = DeviceType::withTrashed()->find($id);
        if (!$deviceType) {
            throw new ModelNotFoundException("Device Type with id {$id} not found");
        }
        $deviceType->forceDelete();
        return $deviceType;
    }
}
