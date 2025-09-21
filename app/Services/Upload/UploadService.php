<?php

namespace App\Services\Upload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadService
{
    private $defaultUploadPath = '';
    private $storageDisks = [
        "uploads" => "uploads",
        "images"  => "images",
        "files"  => "files",
        "public"  => "public"
    ];

    public function uploadFile(UploadedFile $uploadedFile, string $uploadPath = null, string $storageDisk = 'public'): null|string
    {
        if(!$uploadedFile instanceof UploadedFile){
            return null;
        }
        $file = $uploadedFile;
        $uploadPath = $uploadPath??$this->defaultUploadPath;

        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileOnServerName = time() . '_' . $fileName;
        $fileExtension = $file->guessExtension();
        $filePath = Storage::disk($this->storageDisks[$storageDisk])->putFileAs($uploadPath, $file, $fileOnServerName . '.' . $fileExtension);

        return $filePath;
    }
}
