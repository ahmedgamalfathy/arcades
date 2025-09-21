<?php

namespace App\Http\Controllers\API\V1\Dashboard\ForgotPassword;

use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\ForgotPasswordSendCode;
use Illuminate\Support\Facades\Mail;
use App\Enums\ResponseCode\HttpStatusCode;

class ResendCodeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try{
            $data = $request->validate([
              'email' => 'required|email|exists:users,email',
            ]);
            $user=User::where('email',$data['email'])->first();
            if($user){
                $code=rand(100000,999999);
                $user->update([
                'code'=>$code,
                'expired_at'=>now()->addMinutes(5)
            ]);
            Mail::to($user->email)->send(new ForgotPasswordSendCode($user, $user->code));
            return ApiResponse::success([], __('crud.created'));
            }else{
            return ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND);
            }
            }catch(\Exception $ex){
                return ApiResponse::error($ex->getMessage(), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
            }
    }
}
