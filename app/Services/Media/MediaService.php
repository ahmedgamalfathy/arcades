<?php
namespace App\Services\Media;

use App\Enums\Media\MediaTypeEnum;
use App\Models\Media\Media;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MediaService {
    public $uploadService;
        public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }
   public  function allMedia($category=null)  {
    if($category == null){
      $media = Media::select('id','path')->get();
    }else{
    $media = Media::select('id','path')->where('category',$category)->get();
    }
    return $media;
    }

    function editMedia($id)  {
    $media = Media::select('id','path')->find($id);
    if(!$media){
        throw new ModelNotFoundException();
    }
    return $media;
    }

    public function createMedia(array $data){
        $path = null;
        if(isset($data['path']) && $data['path'] instanceof UploadedFile){
          $path =  $this->uploadService->uploadFile($data['path'], 'media');
        }
      $media=Media::create([
        'path'=>$path,
        'type'=>$data['type']??MediaTypeEnum::PHOTO->value,
        'category'=>$data['category'],
      ]);
      return $media;
    }
    public function updateMedia(int $id,$data){
    $media = Media::find($id);
    if(!$media){
        throw new ModelNotFoundException();
    }
    $path = null;
    if($media->path){
        Storage::disk('public')->delete($media->getRawOriginal('path'));
    }
    if(isset($data['path']) && $data['path'] instanceof UploadedFile){
    $path =$this->uploadService->uploadFile($data['path'], 'media');
    }
    $media->type=$data['type'];
    $media->path = $path;
    $media->category = $data['category'];
    $media->save();
    return $media;
    }

    public function deleteMedia(int $id)
    {
        $media = Media::find($id);
        if (! $media) {
            throw new ModelNotFoundException();
        }
        if (! is_null($media->category)) {
            return;
        }
        if ($media->path) {
            Storage::disk('public')->delete($media->getRawOriginal('path'));
        }
        $media->delete();
    }

}
