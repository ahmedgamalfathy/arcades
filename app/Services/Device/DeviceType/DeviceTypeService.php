<?php
namespace App\Services\Device\DeviceType;

use Illuminate\Http\Request;
use App\Models\Device\DeviceType\DeviceType;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeviceTypeService
{
    public function allDeviceTypes(Request $request)
    {
      $query = $request->query('perPage',10);
      $deviceTypes =DeviceType::with('deviceTimes')->cursorPaginate($query);
      return $deviceTypes;
    }
    public function editDeviceType(int $id)
    {
        $deviceType = DeviceType::find($id);
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
    public function deleteDeviceType(int $id )
    {
        $deviceType = DeviceType::find($id);
        if(!$deviceType){
        throw new ModelNotFoundException("Device Type with id {$id} not found");
        }
        $deviceType->delete();
        return  $deviceType;
    }
}
