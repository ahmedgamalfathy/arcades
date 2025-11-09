<?php
namespace App\Services\Select;

use App\Models\Device\DeviceType\DeviceType;
use App\Models\Device\Device;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Enums\BookedDevice\BookedDeviceEnum;

class DeviceTypeSelectService{
    //getDevicesAvailable
    public function getDevicesAvailable(){
        $devices = Device::on('tenant')->whereNotIn('id',BookedDevice::on('tenant')->where(function($query){
            $query->where('end_date_time',null)->orWhere('end_date_time','!=',null);
        })->where('status','!=',BookedDeviceEnum::FINISHED->value)->pluck('device_id'))
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
    public function getTimesByDeviceID($deviceId=null){
        if($deviceId == null){
            return DeviceTime::on('tenant')->get(['id as value', 'name as label']);
        }
        $times = DeviceTime::on('tenant')->where('device_id',$deviceId)
        ->orWhere('device_type_id',$deviceId)->get(['id as value', 'name as label']);
        return $times;
    }
}

