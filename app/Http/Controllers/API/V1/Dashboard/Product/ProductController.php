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
// implements HasMiddleware
class ProductController extends Controller implements HasMiddleware
{
       protected $productService;


    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public static function middleware(): array
    {
        return [//products , create_products,edit_product,update_product ,destroy_product
            new Middleware('auth:api'),
            new Middleware('permission:products', only:['index']),
            new Middleware('permission:create_products', only:['create']),
            new Middleware('permission:edit_product', only:['edit']),
            new Middleware('permission:update_product', only:['update']),
            new Middleware('permission:destroy_product', only:['destroy']),
            new Middleware('tenant'),
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

    public function restore(int $id)
    {
        try {
            $this->productService->restoreProduct($id);
            return ApiResponse::success([], __('crud.restored'));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'), $th->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    public function forceDelete(int $id)
    {
        try {
            $this->productService->forceDeleteProduct($id);
            return ApiResponse::success([], __('crud.deleted'));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'), $th->getMessage(), HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
