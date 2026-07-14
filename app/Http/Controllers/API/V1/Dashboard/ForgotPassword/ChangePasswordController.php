<?php

namespace App\Http\Controllers\API\V1\Dashboard\ForgotPassword;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Auth\ForgotPasswordService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function __construct(private ForgotPasswordService $forgotPasswordService)
    {
    }

    public function __invoke(Request $request)
    {
        try {
            $data = $request->validate([
                'code' => 'required|exists:users,code',
                'email' => 'required|email|exists:users,email',
                'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed'],
            ]);

            $result = $this->forgotPasswordService->changePassword(
                $data['email'],
                $data['code'],
                $data['password']
            );

            if ($result === 'not_found') {
                return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
            }

            if ($result === 'expired') {
                return ApiResponse::error('Time of code is expired ,please resend code again!', [], HttpStatusCode::UNPROCESSABLE_ENTITY);
            }

            return ApiResponse::success([], __('auth.change_password'));
        } catch (ValidationException $ex) {
            return ApiResponse::error(__('validation.validation_error'), $ex->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $ex) {
            return ApiResponse::exception($ex);
        }
    }
}
