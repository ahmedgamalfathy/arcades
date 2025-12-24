<?php
namespace App\Services\Select;

use App\Models\Daily\Daily;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Models\Timer\SessionDevice\SessionDevice;

class SessionSelectService
{
//dailynow
    public function getSessionGroup()
    {
        return  SessionDevice::on('tenant')->whereHas('bookedDevices', function ($q) {
        $q->where('status','!=',0);
        })->where('type',SessionDeviceEnum::GROUP->value)->get(['id as value', 'name as label']);
    }

}
