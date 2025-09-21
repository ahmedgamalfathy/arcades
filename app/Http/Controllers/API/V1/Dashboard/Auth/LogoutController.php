<?php

namespace App\Http\Controllers\API\V1\Dashboard\Auth;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Enums\ResponseCode\HttpStatusCode;

class LogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        if (Auth::check()) {
            auth()->user()->tokens()->delete(); // Revoke all tokens
        }else { return ApiResponse::error(__('auth.not_authenticated'), [], HttpStatusCode::UNAUTHORIZED);}
        return ApiResponse::success([], __('auth.logged_out'));
    }



}


