<?php

namespace App\Http\Controllers\API\V1\Dashboard\Auth;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        if (Auth::check()) {
            auth()->user()->tokens()->delete(); // Revoke all tokens
        } else {
            return ApiResponse::error(__('auth.not_authenticated'), [], HttpStatusCode::UNAUTHORIZED);
        }

        return ApiResponse::success([], __('auth.logged_out'));
    }
}
