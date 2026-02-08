<?php
namespace App\Services\Product;

use App\Enums\StatusEnum;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Product\Product;
use App\Enums\Media\MediaTypeEnum;
use App\Services\Media\MediaService;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filters\Product\FilterProduct;
use App\Services\Upload\UploadService;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductService {
    public $uploadService;
    public $mediaService;
   public function __construct( MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }
  public function allProducts(Request $request)
  {
    $perPage= $request->query('perPage',10);
    $products=QueryBuilder::for(Product::class)
     ->allowedFilters([
           AllowedFilter::custom('search', new FilterProduct),
      ])->cursorPaginate($perPage);
    return $products;
  }
  public function editProduct($id)
  {
    $product= Product::find($id);
    if(!$product){
        throw new ModelNotFoundException();
    }
    return $product;
  }
    public function createProduct(array $data)
  {
    if(isset($data['path']) && $data['path'] instanceof UploadedFile){
            $media = $this->mediaService->createMedia([
                'path'     => $data['path'],
                'type'     => MediaTypeEnum::PHOTO->value,
                'category' => null,
            ]);
    }
     $product =Product::create([
      'name'=>$data['name'],
      'price'=>$data['price'],
      'status'=>$data['status']??StatusEnum::ACTIVE->value,
      'media_id'=>$media->id??null,
     ]);
     return $product;
  }
    public function updateProduct(int $id,array $data)
  {
        $product =Product::find($id);
        if(!$product){
            throw new ModelNotFoundException();
        }
        if(isset($data['path']) && $data['path'] instanceof UploadedFile){
            if ($product->media_id) {
                $media = $this->mediaService->updateMedia($product->media_id, [
                    'path'     => $data['path'],
                    'type'     => MediaTypeEnum::PHOTO->value,
                    'category' => null,
                ]);
                // $this->mediaService->deleteMedia($product->media_id);
            } else {
                $media = $this->mediaService->createMedia([
                    'path'     => $data['path'],
                    'type'     => MediaTypeEnum::PHOTO->value,
                    'category' => null,
                ]);
            }
            $product->media_id =$media?->id??null;
        }
        $product->name =$data['name'];
        $product->price =$data['price'];
        $product->status =$data['status']??$product->status;
        $product->save();
     return $product;
  }
    public function deleteProduct(int $id)
  {
    $product =Product::find($id);
    if(!$product){
        throw new ModelNotFoundException();
    }
    // if($product->media_id){
    //     $this->mediaService->deleteMedia($product->media_id);
    // }
    $product->delete();
  }
    public function restoreProduct(int $id)
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();
        return $product;
    }
    public function forceDeleteProduct(int $id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        if($product->media_id){
            $this->mediaService->deleteMedia($product->media_id);
        }
        $product->forceDelete();
    }
}
