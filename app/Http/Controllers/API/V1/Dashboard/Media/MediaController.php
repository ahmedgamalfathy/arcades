<?php

namespace App\Http\Controllers\API\V1\Dashboard\Media;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Media\MediaService;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\Media\CreateMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use Illuminate\Routing\Controllers\HasMiddleware;

class MediaController extends Controller  implements HasMiddleware
{
    /**
     * Display a listing of the resource.
     */
    public $mediaService;
    public function __construct(MediaService $mediaService) {
     $this->mediaService =$mediaService;
    }
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('tenant'),
        ];
    }
    public function index(Request $request)
    {
        $category = $request->query('category');
        $media =$this->mediaService->allMedia($category);
        return ApiResponse::success($media);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateMediaRequest $createMediaRequest)
    {
        $this->mediaService->createMedia($createMediaRequest->validated());
        return ApiResponse::success([],__('crud.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $media =$this->mediaService->editMedia($id);
        return ApiResponse::success($media);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMediaRequest $updateMediaRequest, int $id)
    {
        $this->mediaService->updateMedia($id,$updateMediaRequest->validated());
        return ApiResponse::success([],__('crud.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->mediaService->deleteMedia($id);
        return ApiResponse::success([],__('crud.deleted'));
    }
}
