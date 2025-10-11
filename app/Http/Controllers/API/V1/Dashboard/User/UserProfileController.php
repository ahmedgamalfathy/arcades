<?php

namespace App\Http\Controllers\API\V1\Dashboard\User;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Enums\Media\MediaTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Auth;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\User\PorfileResource;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Requests\User\UpdateUserProfileRequest;

class UserProfileController extends Controller implements HasMiddleware
{
    protected $uploadService;
    protected $mediaService;
    public function __construct(UploadService $uploadService, MediaService $mediaService)
    {
        $this->uploadService = $uploadService;
        $this->mediaService = $mediaService;
    }

    public static function middleware(): array
    {
        return [// profile ,profileUpdate
            new Middleware('auth:api'),
        ];
    }


    /**
     * Show the form for editing the specified resource.
     */

    public function show(Request $request)
    {

        return ApiResponse::success(new PorfileResource($request->user()));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserProfileRequest $request)
    {
        $superAdmin =Auth::user();
        $authUser = $request->user();
        $userData = $request->validated();
        $oldMediaId = $authUser->media_id;
        $mediaId = $oldMediaId;
        if (isset($userData['mediaFile']) && $userData['mediaFile'] instanceof UploadedFile) {
                $media = $this->mediaService->createMedia([
                    'path' => $userData['mediaFile'],
                    'type' => MediaTypeEnum::PHOTO,
                    'category'=>null,
                ]);
                $mediaId = $media->id;
                if ($oldMediaId) {
                $this->mediaService->deleteMedia($mediaId);
                }
        }
        elseif (isset($userData['mediaId']) && $userData['mediaId'] != $oldMediaId) {
                $mediaId = $userData['mediaId'];
                if ($oldMediaId) {
                    $this->mediaService->deleteMedia($mediaId);
                }
        }
        $authUser->name = $userData['name'].$superAdmin->app_key;
        $authUser->media_id =$mediaId;
        $authUser->save();

        return ApiResponse::success([], __('crud.updated'));
    }


}
