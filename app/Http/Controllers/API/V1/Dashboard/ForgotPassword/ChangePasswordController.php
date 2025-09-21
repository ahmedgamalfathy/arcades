<?php

namespace App\Http\Controllers\API\V1\Dashboard\ForgotPassword;

use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Enums\ResponseCode\HttpStatusCode;

class ChangePasswordController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
          DB::beginTransaction();
        try{
        $data=$request->validate([
            'code' => 'required|exists:users,code',
            'email' => 'required|email|exists:users,email',
            "password"=>["required",
            Password::min(8)->mixedCase()->numbers(),'confirmed'],
        ]);
        $user=User::where('email',$data['email'])->where('code',$data['code'])->first();
        if(!$user){
           return ApiResponse::error(__('crud.not_found'),[], HttpStatusCode::NOT_FOUND);
        }
        if($user->expired_at < now()){
            return ApiResponse::error('Time of code is expired ,please resend code again!', [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        }
        $user->update([
            'password'=>Hash::make($data['password']),
            'code'=>null,
            'expired_at'=>null
        ]);
        $user->tokens()->delete();
        DB::commit();
        return ApiResponse::success([], __('crud.updated'));
        }catch(\Exception $ex){
            DB::rollBack();
            return ApiResponse::error($ex->getMessage(), [], HttpStatusCode::UNPROCESSABLE_ENTITY);
        }
    }

}
