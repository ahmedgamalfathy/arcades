<?php

namespace App\Http\Controllers\API\V1\Dashboard\User;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Enums\ResponseCode\HttpStatusCode;
use App\Http\Requests\User\ChangePasswordRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
class ChangeCurrentPasswordController extends Controller implements HasMiddleware
{
   public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }
    /**
     * Handle the incoming request.
     */
    public function __invoke(ChangePasswordRequest $request)
    {
        $authUser = $request->user();

        if (!Hash::check($request->currentPassword, $authUser->password)) {
            return ApiResponse::error(__('passwords.current_password_error'), [],HttpStatusCode::UNPROCESSABLE_ENTITY);
        }

        // Update password securely
        $authUser->update([
            'password' => Hash::make(value: $request->password),
        ]);

        $authUser->tokens()->delete();

        return ApiResponse::success([], __('crud.updated'));
    }
}
