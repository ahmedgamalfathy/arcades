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

class DeviceService
{
    protected $mediaService;
    public function __construct(MediaService $mediaService){
      $this->mediaService = $mediaService;
    }
    public function allDevices(Request $request)
    {
      $query = $request->query('perPage',10);
      $devices =Device::with('media','deviceTimes','maintenances')
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
    protected function deleteUnusedMedia($mediaId, $excludeDeviceId = null) {
        $query = Device::where('media_id', $mediaId);
        if ($excludeDeviceId) {
            $query->where('id', '!=', $excludeDeviceId);
        }
        $isUsed = $query->exists();
        if (!$isUsed) {
          $this->mediaService->deleteMedia($mediaId);
        }
    }
}

