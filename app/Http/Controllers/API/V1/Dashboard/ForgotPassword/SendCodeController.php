<?php

namespace App\Http\Controllers\API\V1\Dashboard\ForgotPassword;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Auth\ForgotPasswordService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SendCodeController extends Controller
{
    public function __construct(private ForgotPasswordService $forgotPasswordService)
    {
    }

    public function __invoke(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            if (!$this->forgotPasswordService->sendCode($data['email'])) {
                return response()->json([
                    'message' => __('crud.not_found'),
                ], 404);
            }

            return ApiResponse::success([], __('auth.send_code'));
        } catch (ValidationException $e) {
            return ApiResponse::error(__('validation.validation_error'), $e->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return ApiResponse::exception($e);
        }
    }
}
