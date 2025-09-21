<?php

namespace App\Http\Requests\User;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Enums\StatusEnum;
use App\Enums\User\UserStatus;
use App\Helpers\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {

        return [
            'name' => 'required',
            'email'=> [
                'required',
                'email',
                'unique:users,email,' . $this->route('user'),
            ],
            'phone' => ['nullable','numeric'],
            'address' => 'nullable',
            // 'isActive' => ['required', new Enum(StatusEnum::class)],
            'roleId'=> 'required',
            'avatar' => [ "nullable","image", "mimes:jpeg,jpg,png,gif,svg,webp","max:5120"],//, "max:2048"
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error('', $validator->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY)
        );
    }

    public function messages()
    {
        return [
            'name.required' => __('validation.custom.required'),
            'email.required' => __('validation.custom.required'),
            'password.required' => __('validation.custom.required'),
        ];
    }

}
