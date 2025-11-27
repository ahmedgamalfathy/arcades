<?php
namespace App\Services\Device\DeviceTime;

use Illuminate\Http\Request;
use App\Models\Device\DeviceTime\DeviceTime;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeviceTimeService
{
    public function allDeviceTimes(Request $request)
    {
      $deviceTypeId=$request->query('deviceTypeId');
      $deviceTimes =DeviceTime::select('id','name','rate')
      ->where('device_type_id',$deviceTypeId)
      ->get();
      return $deviceTimes;
    }
    public function editDeviceTime(int $id)
    {
        $deviceTime = DeviceTime::find($id);
        if(!$deviceTime){
        throw new ModelNotFoundException("Device Time with id {$id} not found");
        }
        return  $deviceTime;
    }
    public function createDeviceTime(array $data)
    {
    $deviceTime = DeviceTime::create([
        'name'=>$data['name'],
        'rate'=>$data['rate'],
        'device_type_id'=>$data['deviceTypeId']??null,
        'device_id'=>$data['deviceId']??null
    ]);
    return $deviceTime;
    }
    public function updateDeviceTime(int $id, array $data)
    {
    $deviceTime = DeviceTime::find($id);
    if(!$deviceTime){
      throw new ModelNotFoundException("Device Time with id {$id} not found");
    }
    $deviceTime->name = $data['name'];
    $deviceTime->rate = $data['rate'];
    $deviceTime->device_type_id = $data['deviceTypeId']??null;
    $deviceTime->device_id = $data['deviceId']??null;
    $deviceTime->save();
    return $deviceTime;
    }
    public function deleteDeviceTime(int $id)
    {
        $deviceTime = DeviceTime::find($id);
        if(!$deviceTime){
        throw new ModelNotFoundException("Device Time with id {$id} not found");
        }
        $deviceTime->delete();
        return  $deviceTime;
    }
}

