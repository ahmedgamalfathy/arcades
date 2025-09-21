<?php

namespace App\Http\Controllers\API\V1\Dashboard\User;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Utils\PaginateCollection;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\ViewUserResource;
use App\Http\Resources\User\AllUserCollection;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\User\ChangePasswordToUpdateUserRequest;

class UserController extends Controller implements HasMiddleware
{
       protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('permission:all_users', only:['index']),
            new Middleware('permission:create_user', only:['create']),
            new Middleware('permission:edit_user', only:['edit']),
            new Middleware('permission:update_user', only:['update']),
            new Middleware('permission:destroy_user', only:['destroy']),
        ];
    }




    public function index(Request $request)
    {
        $users = $this->userService->allUsers();
        return ApiResponse::success(new AllUserCollection(PaginateCollection::paginate($users, $request->pageSize?$request->pageSize:10)));

    }

    /**
     * Show the form for creating a new resource.
     */

    public function store(CreateUserRequest $createUserRequest)
    {
        try {
            DB::beginTransaction();

            $this->userService->createUser($createUserRequest->validated());

            DB::commit();

            return ApiResponse::success([], __('crud.created'));


        } catch (\Exception $e) {
            DB::rollBack();
           return  ApiResponse::error(__('crud.server_error'),$e->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }


    }

    /**
     * Show the form for editing the specified resource.
     */

    public function show(int $id)
    {
        try {
            $user  =  $this->userService->editUser($id);
            return ApiResponse::success(new UserResource($user));
        }catch(ModelNotFoundException $e){
            return  ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Exception $e) {
          return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }



    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id,UpdateUserRequest $updateUserRequest)
    {
        try {
            DB::beginTransaction();
            $this->userService->updateUser($id, $updateUserRequest->validated());
            DB::commit();
            return ApiResponse::success([], __('crud.updated'));

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }


    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $userId)
    {

        try {
            DB::beginTransaction();
            $this->userService->deleteUser($userId);
            DB::commit();
            return ApiResponse::success([], __('crud.deleted'));
        } catch(ModelNotFoundException $e){
            return  ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Exception $e) {
            DB::rollBack();
          return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }

    public function changeStatus(Request $request)
    {

        try {
            DB::beginTransaction();
            $this->userService->changeUserStatus($request->userId, $request->status);
            DB::commit();
            return ApiResponse::success([],__('messages.success.updated'));
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(__('crud.server_error'),[],HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

    }
    public function userChangePassword(ChangePasswordToUpdateUserRequest $request){
        try {
            $this->userService->changePassword($request->validated());
            return ApiResponse::success([], __('crud.updated'));
        }catch(ModelNotFoundException $e){
            return  ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        } catch (\Exception $e) {
          return ApiResponse::error(__('crud.server_error'),$e->getMessage(),HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }

}
