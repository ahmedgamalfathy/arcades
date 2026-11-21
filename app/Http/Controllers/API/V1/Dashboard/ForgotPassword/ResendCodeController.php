<?php

namespace App\Http\Controllers\API\V1\Dashboard\ForgotPassword;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Auth\ForgotPasswordService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResendCodeController extends Controller
{
    public function __construct(private ForgotPasswordService $forgotPasswordService) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
            ]);

            $this->forgotPasswordService->sendCode($data['email']);

            return ApiResponse::success([], __('auth.send_code'));
        } catch (ValidationException $ex) {
            return ApiResponse::error(__('validation.validation_error'), $ex->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $ex) {
            return ApiResponse::exception($ex);
        }
    }
}
