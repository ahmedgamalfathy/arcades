<?php

namespace App\Http\Controllers\API\V1\Dashboard\ForgotPassword;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Auth\ForgotPasswordService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyCodeController extends Controller
{
    public function __construct(private ForgotPasswordService $forgotPasswordService)
    {
    }

    public function __invoke(Request $request)
    {
        try {
            $data = $request->validate([
                'code' => 'required',
                'email' => 'required|email|exists:users,email',
            ]);

            $result = $this->forgotPasswordService->verifyCode($data['email'], $data['code']);

            if ($result === 'invalid') {
                return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
            }

            if ($result === 'expired') {
                return ApiResponse::error('Time of code is expired ,please resend code again!', [], HttpStatusCode::UNPROCESSABLE_ENTITY);
            }

            return ApiResponse::success([], __('auth.verify_code'));
        } catch (ValidationException $th) {
            return ApiResponse::error(__('validation.validation_error'), $th->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }
    }
}
