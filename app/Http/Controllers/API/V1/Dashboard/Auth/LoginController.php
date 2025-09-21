<?php

namespace App\Http\Controllers\API\V1\Dashboard\Auth;

use App\Http\Resources\User\LoginProfileResource;
use App\Models\User;
use App\Enums\StatusEnum;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Services\UserRolePremission\UserPermissionService;

class LoginController extends Controller
{
    public function __construct( protected UserPermissionService $userPermissionService)
    {
    }
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginUserRequest $loginUserRequest)
    {
        $data = $loginUserRequest->validated();
         try {
            $user = User::where('email', $data['email'])->first();
            if (!$user || !Hash::check($data['password'], $user->password)) {
                return ApiResponse::error(__('auth.failed'), [], HttpStatusCode::UNAUTHORIZED);
            }
            if ($user->is_active == StatusEnum::INACTIVE) {
                return ApiResponse::error(__('auth.inactive_account'), [], HttpStatusCode::UNAUTHORIZED);
            }
            $token = $user->createToken('auth_token')->plainTextToken;
            $expiration = config('sanctum.expiration'); // بالدقايق
            return ApiResponse::success([

                'tokenDetails' => [
                    'token' => $token,
                    'expiresIn' => $expiration
                ],
                'profile' =>new LoginProfileResource($user),
                'role' => $user->roles->first()->name ?? 'guest',
                'permissions' => $this->userPermissionService->getUserPermissions($user) ?? [],
            ]);

        } catch (\Throwable $th) {
          return ApiResponse::error($th->getMessage(), [], HttpStatusCode::INTERNAL_SERVER_ERROR);
        }
    }
}
