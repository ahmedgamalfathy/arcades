<?php

namespace App\Http\Controllers\API\V1\Dashboard\Expense;

use App\Enums\Expense\ExpenseTypeEnum;
use App\Http\Resources\Expense\AllExpenseResource;
use Exception;
use Throwable;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Expense\ExpenseService;
use App\Enums\ResponseCode\HttpStatusCode;

use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Requests\Expense\CreateExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\Expense\ExpenseResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ExpenseController extends Controller implements HasMiddleware
{
       protected $expenseService;


    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public static function middleware(): array
    {//internalExpenses,externalExpenses, forceDelete,restore,create_expenses ,edit_expense ,update_expense,destroy_expense
        return [
            new Middleware('auth:api'),
            new Middleware('permission:internalExpenses', only:['internalExpenses']),
            new Middleware('permission:externalExpenses', only:['externalExpenses']),
            new Middleware('permission:create_expenses', only:['create']),
            new Middleware('permission:edit_expense', only:['edit']),
            new Middleware('permission:update_expense', only:['update']),
            new Middleware('permission:destroy_expense', only:['destroy']),
            new Middleware('permission:destroy_restore', only:['restore']),
            new Middleware('permission:destroy_forceDelete', only:['forceDelete']),
            new Middleware('tenant'),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function internalExpenses(Request $request)
    {
        $expenses = $this->expenseService->allExpenses($request);
        return ApiResponse::success(new AllExpenseResource($expenses));
    }

    public function externalExpenses(Request $request)
    {
        $expenses = $this->expenseService->allExpenses($request);
        return ApiResponse::success(new AllExpenseResource($expenses));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateExpenseRequest $createExpenseRequest)
    {
        try {
            DB::beginTransaction();
               $this->expenseService->createExpense($createExpenseRequest->validated());
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
         $expense =  $this->expenseService->editExpense($id);
            return ApiResponse::success(new ExpenseResource($expense));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $updateExpenseRequest, int $id)
    {
        try {
            DB::beginTransaction();
            $this->expenseService->updateExpense($id,$updateExpenseRequest->validated());
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
    public function destroy(string $id)
    {
        try{
            $this->expenseService->deleteExpense($id);
            return ApiResponse::success([],__('crud.deleted'));
        }catch (ModelNotFoundException $th) {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function restore($id){
        try {
            $this->expenseService->restoreExpense($id);
            return ApiResponse::success([],__('crud.restore'));
        }catch(ModelNotFoundException $e){
            return apiResponse::error(__('crud.not_found'),[], HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
    public function forceDelete($id)
    {
        try {
            $this->expenseService->forceDeleteExpense($id);
            return ApiResponse::success([],__('crud.deleted'));
        } catch(ModelNotFoundException $e){
            return apiResponse::error(__('crud.not_found'),[], HttpStatusCode::NOT_FOUND);
        }catch (\Throwable $th) {
            return ApiResponse::error(__('crud.server_error'),$th->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
