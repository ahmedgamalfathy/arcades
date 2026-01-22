<?php
namespace App\Services\Timer;

use App\Models\Timer\SessionDevice\SessionDevice;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Filters\Timer\FilterSessionDevice;
use App\Filters\Timer\FilterTypeSessionDevice;

class SessionDeviceService
{
    public function getSessionDevices( Request $request)
    {
        $perPage= $request->query('perPage',10);
        $sessions=QueryBuilder::for(SessionDevice::class)
        ->where('daily_id', $request->query('dailyId'))
        ->allowedFilters([
            AllowedFilter::custom('search', new FilterSessionDevice),
            AllowedFilter::exact('type', 'type'),
            AllowedFilter::custom('bookedDevicesStatus', new FilterTypeSessionDevice),
        ])
        ->whereHas('bookedDevices', function ($query) {
        $query->where('status', '!=', 0);
        })
        ->with(['bookedDevices' => function ($query) {
        $query->where('status', '!=', 0);
        }])
        ->cursorPaginate($perPage);
        return $sessions;
    }
    public function createSessionDevice(array $data)
    {
        return SessionDevice::create($data);
    }

    public function editSessionDevice(int $id)
    {
        // return SessionDevice::with('bookedDevices')->findOrFail($id);
    return SessionDevice::with([
        'bookedDevices',
        'bookedDevicesLatest'
    ])->findOrFail($id);
    }

    public function updateSessionDevice(int $id, array $data)
    {
        $sessionDevice=SessionDevice::findOrFail($id);
        $sessionDevice->name = $data['name'];
        $sessionDevice->save();
        return $sessionDevice;
    }

    public function deleteSessionDevice(int $id): void
    {
        $sessionDevice=SessionDevice::findOrFail($id);
        $sessionDevice->delete();
    }
}


