<?php
namespace App\Services\Select;

use App\Models\Device\Device;
use App\Models\Maintenance\Maintenance;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;

class DeviceTypeSelectService{


public function getDevicesAvailable($deviceTypeId)
{
    $bookedDevices = BookedDevice::on('tenant')
        ->where('status', '!=', BookedDeviceEnum::FINISHED->value)
        ->pluck('device_id');
    $devicesInMaintenance = Maintenance::on('tenant')->pluck('device_id');
    $devices = Device::on('tenant')
        ->whereNotIn('id', $bookedDevices)
        ->whereNotIn('id', $devicesInMaintenance)
        ->where('device_type_id', $deviceTypeId)
        ->get(['id as value', 'name as label']);

    return $devices;
}

    //getDevicesAvailable
    public function getDevicesAvailableOld($deviceTypeId){
        $devices = Device::on('tenant')->whereNotIn('id',BookedDevice::on('tenant')->where(function($query){
            $query->where('end_date_time',null)->orWhere('end_date_time','!=',null);
        })->where('status','!=',BookedDeviceEnum::FINISHED->value)->pluck('device_id'))
        ->where('device_type_id',$deviceTypeId)
        ->get(['id as value', 'name as label']);
        return $devices;
    }
    public function getDeviceType(){
        $deviceType = DeviceType::on('tenant')->get(['id as value', 'name as label']);
        return $deviceType;
    }
    public function getDevicesByDeviceType($deviceTypeId=null){
        if($deviceTypeId == null){
            return Device::on('tenant')->get(['id as value', 'name as label']);
        }
        $devices = Device::on('tenant')->where('device_type_id',$deviceTypeId)->get(['id as value', 'name as label']);
        return $devices;
    }
    public function devicesAvailableByDeviceType($deviceTypeId=null){
        $bookedDevices = BookedDevice::on('tenant')->where(function($query){
            $query->where('end_date_time',null)->orWhere('end_date_time','!=',null);
        })->where('status','!=',BookedDeviceEnum::FINISHED->value)->pluck('device_id');
        $devices = Device::on('tenant')->where('device_type_id',$deviceTypeId)->whereNotIn('id',$bookedDevices)->get(['id as value', 'name as label']);
        return $devices;
    }
    public function getTimesByDeviceID($deviceId){
    return Device::on('tenant')->with('deviceTimes')
        ->where('id', $deviceId)
        ->get(['id as value', 'name as label']);
    }
}

