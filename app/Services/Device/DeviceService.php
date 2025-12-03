<?php
namespace App\Services\Device;

use Log;
use Exception;
use Illuminate\Http\Request;
use App\Models\Device\Device;
use App\Enums\Media\MediaTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Services\Media\MediaService;
use App\Enums\Device\DeviceStatusEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\Device\DeviceTime\DeviceTimeService;
use App\Models\Device\DeviceTime\DeviceTime;

class DeviceService
{
    protected $mediaService;
    protected $deviceTimeService;
    public function __construct(MediaService $mediaService ,DeviceTimeService $deviceTimeService){
      $this->mediaService = $mediaService;
      $this->deviceTimeService = $deviceTimeService;
    }
    public function allDevices(Request $request)
    {
      $query = $request->query('perPage',10);
      $devices =Device::with('media','deviceTimes','deviceTimeSpecial','maintenances')
      ->cursorPaginate($query);
      return $devices;
    }
    public function editDevice(int $id)
    {
        $device = Device::with('media','deviceTimes')->find($id);
        if(!$device){
        throw new ModelNotFoundException("Device  with id {$id} not found");
        }
        return  $device;
    }
    public function createDevice(array $data)
    {
     DB::beginTransaction();
        try {
            $mediaId = null;
            if (isset($data['mediaFile']) && $data['mediaFile'] instanceof UploadedFile) {
                $media = $this->mediaService->createMedia([
                    'path' => $data['mediaFile'],
                    'type' => MediaTypeEnum::PHOTO,
                    'category'=>null,
                ]);
                $mediaId = $media->id;
            }
            elseif (isset($data['mediaId'])) {
                $mediaId = $data['mediaId'];
            }
            // إنشاء الجهاز
            $device = Device::create([
                'name' => $data['name'],
                'device_type_id' => $data['deviceTypeId'],
                'media_id' => $mediaId,
                'status' => DeviceStatusEnum::AVAILABLE->value,
            ]);
            if (isset($data['deviceTimeIds'])) {
                $device->deviceTimes()->attach($data['deviceTimeIds']);
            }
            if (isset($data['deviceTimeSpecial'])) {
                foreach ($data['deviceTimeSpecial'] as $deviceTimeSpecial) {
                    if($deviceTimeSpecial['name'] ){
                        $existingDeviceTime = DeviceTime::where('name', $deviceTimeSpecial['name'])
                        ->where('device_id', $device->id)
                        ->first();
                        if ($existingDeviceTime) {
                            throw new Exception("Device time with name {$deviceTimeSpecial['name']} already exists for this device");
                        }
                    }
                    $deviceTimeSpecial['deviceId'] = $device->id;
                    $this->deviceTimeService->createDeviceTime($deviceTimeSpecial);
                }
            }
            DB::commit();
            return $device;
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
    public function updateDevice(int $id, array $data)
    {
        $device = Device::find($id);
        if(!$device){
        throw new ModelNotFoundException("Device  with id {$id} not found");
        }
        $oldMediaId = $device->media_id;
        $mediaId = $oldMediaId;
        if (isset($data['mediaFile']) && $data['mediaFile'] instanceof UploadedFile) {
                $media = $this->mediaService->createMedia([
                    'path' => $data['mediaFile'],
                    'type' => MediaTypeEnum::PHOTO,
                ]);
                $mediaId = $media->id;
                if ($oldMediaId) {
                $this->mediaService->deleteMedia($mediaId);
                }
        }
        elseif (isset($data['mediaId']) && $data['mediaId'] != $oldMediaId) {
                $mediaId = $data['mediaId'];
                if ($oldMediaId) {
                    $this->mediaService->deleteMedia($mediaId);
                }
        }
        $device->update([
        'name' => $data['name'],
        'device_type_id' => $data['deviceTypeId'],
        'media_id' => $mediaId
        ]);
        if (isset($data['deviceTimeIds'])) {
            $device->deviceTimes()->sync($data['deviceTimeIds']);
        }
        if (isset($data['deviceTimeSpecial'])) {
        foreach ($data['deviceTimeSpecial'] as $deviceTimeSpecial) {
            if ($deviceTimeSpecial['actionStatus'] !== 'delete' && isset($deviceTimeSpecial['name'])) {
                $existingDeviceTime = DeviceTime::where('name', $deviceTimeSpecial['name'])
                    ->where('device_id', $device->id)
                    ->first();
                if ($existingDeviceTime) {
                    throw new Exception("Device time with name {$deviceTimeSpecial['name']} already exists for this device");
                }
            }
            if ($deviceTimeSpecial['actionStatus'] == 'update' && isset($deviceTimeSpecial['timeTypeId'])) {
                  $deviceTimeSpecial['deviceId'] = $device->id;
                $this->deviceTimeService->updateDeviceTime($deviceTimeSpecial['timeTypeId'], $deviceTimeSpecial);
            } elseif ($deviceTimeSpecial['actionStatus'] == 'create') {
                $deviceTimeSpecial['deviceId'] = $device->id;
                $this->deviceTimeService->createDeviceTime($deviceTimeSpecial);
            } elseif ($deviceTimeSpecial['actionStatus'] == 'delete' && isset($deviceTimeSpecial['timeTypeId'])) {
                $this->deviceTimeService->deleteDeviceTime($deviceTimeSpecial['timeTypeId']);
            }
        }
    }
    return $device;
    }
    public function deleteDevice(int $id)
    {
        $device = Device::find($id);
        if(!$device){
        throw new ModelNotFoundException("Device  with id {$id} not found");
        }
        $device->delete();
        return  $device;
    }
    public function changeDeviceStatus(int $id, array $data): void
    {
        $device =Device::find($id);
        if(!$device){
        throw new ModelNotFoundException("Device  with id {$id} not found");
        }
        $device->status =DeviceStatusEnum::from($data['status'])->value;
        $device->save();

    }
    public function getTimesByDeviceId(int $deviceId)
    {
        $device = Device::with('deviceTimes')->find($deviceId);
        if(!$device){
        throw new ModelNotFoundException("Device  with id {$deviceId} not found");
        }
        return $device->deviceTimes;
    }
}

