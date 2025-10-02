<?php
namespace App\Services\Maintenance;

use App\Models\Maintenance\Maintenance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;


class MaintenanceServcie {
 public function allMaintenances(Request $request) {
    $deviceId =$request->query('deviceId');
   if (!$deviceId) {
        $maintenances = Maintenance::get();
    } else {
        $maintenances = Maintenance::where('device_id', $deviceId)->get();
    }
    return $maintenances;
 }
 public function editMaintenance(int $id){
    $maintenance = Maintenance::find($id);
    if(!$maintenance){
      throw new ModelNotFoundException("maintenance with id {$id} not found");
    }
    return $maintenance;
 }
 public function createMaintenance(array $data){
    $maintenance = Maintenance::create([
        'user_id'=>auth('api')->id()??null,
        'device_id'=>$data['deviceId'],
        'title'=>$data['title'],
        'price'=>$data['price'],
        'date'=>$data['date'],
        'note'=>$data['note']??null
    ]);
    return $maintenance;
 }
 public function updateMaintenance(int $id , array $data){
    $maintenance = Maintenance::find($id);
    if(!$maintenance){
      throw new ModelNotFoundException("maintenance with id {$id} not found");
    }        //device_id , title , price , date , note
    $maintenance->user_id =auth('api')->id()??null;
    $maintenance->device_id = $data['deviceId'];;
    $maintenance->title = $data['title'];
    $maintenance->price = $data['price'];
    $maintenance->date = $data['date'];
    $maintenance->note = $data['note']??null;
    $maintenance->save();
    return $maintenance;
 }
 public  function deleteMaintenance(int $id){
    $maintenance = Maintenance::find($id);
    if(!$maintenance){
      throw new ModelNotFoundException("maintenance with id {$id} not found");
    }
    $maintenance->delete();
    return  $maintenance;

 }

}
