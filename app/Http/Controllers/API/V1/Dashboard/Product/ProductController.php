<?php

namespace App\Http\Controllers\API\V1\Dashboard\Product;

use Throwable;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Product\ProductService;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Resources\Product\ProductResource;
use App\Http\Requests\Product\CreateProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\AllProductResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller implements HasMiddleware
{
       protected $productService;


    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            // new Middleware('permission:all_products', only:['index']),
            // new Middleware('permission:create_product', only:['create']),
            // new Middleware('permission:edit_product', only:['edit']),
            // new Middleware('permission:update_product', only:['update']),
            // new Middleware('permission:destroy_product', only:['destroy']),
        ];
    }


    public function index(Request $request)
    {
        $products= $this->productService->allProducts($request);
        return ApiResponse::success(new AllProductResource($products));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateProductRequest $createProductRequest)
    {
        try {
            DB::beginTransaction();
            $this->productService->createProduct($createProductRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (Throwable $th) {
            DB::rollBack( );
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {

        try {
            $product= $this->productService->editProduct($id);
            return ApiResponse::success(new ProductResource($product));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id,UpdateProductRequest $updateProductRequest)
    {
        try {
            DB::beginTransaction();
            $this->productService->updateProduct($id,$updateProductRequest->validated());
            DB::commit();
            return ApiResponse::success([], __('crud.updated'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int  $id)
    {

        try{
            $this->productService->deleteProduct($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
