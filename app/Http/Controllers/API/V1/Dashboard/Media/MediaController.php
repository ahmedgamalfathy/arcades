<?php

namespace App\Http\Controllers\API\V1\Dashboard\Media;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\CreateMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Services\Media\MediaService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public $mediaService;
    public function __construct(MediaService $mediaService) {
     $this->mediaService =$mediaService;
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
