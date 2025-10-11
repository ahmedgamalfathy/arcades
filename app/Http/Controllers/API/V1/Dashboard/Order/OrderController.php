<?php

namespace App\Http\Controllers\Api\V1\Dashboard\Order;

use App\Enums\Order\OrderTypeEnum;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Utils\PaginateCollection;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Order\OrderService;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Http\Resources\Order\OrderResource;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\Order\AllOrderCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderController extends Controller implements HasMiddleware
{
    protected $orderService;
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }
    public static function middleware(): array
    {//orders ,create_orders , edit_order ,update_order ,destroy_order
        return [
            new Middleware('auth:api'),
            new Middleware('permission:internalOrders', only:['internalOrders']),
            new Middleware('permission:externalOrders', only:['externalOrders']),
            new Middleware('permission:create_orders', only:['create']),
            new Middleware('permission:edit_order', only:['edit']),
            new Middleware('permission:update_order', only:['update']),
            new Middleware('permission:destroy_order', only:['destroy']),
            new Middleware('tenant'),
        ];
    }

     public function internalOrders(Request $request)
    {
        $orders = $this->orderService->allOrders($request,OrderTypeEnum::INTERNAL->value);
        return ApiResponse::success(new AllOrderCollection($orders));
    }

    public function externalOrders(Request $request)
    {
        $orders = $this->orderService->allOrders($request,OrderTypeEnum::EXTERNAL->value);
        return ApiResponse::success(new AllOrderCollection($orders));
    }

    public function show($id)
    {
        try {
            $order=$this->orderService->editOrder($id);
            return ApiResponse::success(new OrderResource($order));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CreateOrderRequest $createOrderRequest)
    {
        try {
            DB::beginTransaction();
              $this->orderService->createOrder($createOrderRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.created'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function update(UpdateOrderRequest $updateOrderRequest, $id)
    {
        try {
            DB::beginTransaction();
               $this->orderService->updateOrder($id, $updateOrderRequest->validated());
            DB::commit();
            return ApiResponse::success([],__('crud.updated'));
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function destroy(int $id)
    {
        try {
            $this->orderService->deleteOrder($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch(ModelNotFoundException $e){
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
}
