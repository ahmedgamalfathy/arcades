<?php

namespace App\Http\Requests\User;

use Closure;
use App\Enums\StatusEnum;
use App\Helpers\ApiResponse;
use App\Services\User\RoleAssignmentService;
use App\Enums\User\UserStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class CreateUserRequest extends FormRequest
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
        'name' => 'required|string',
            'email' => [
                'required',
                Rule::anyOf([
                    ['string', 'email','unique:users,email'],
                    ['string', 'min:3','unique:users,email'],
                ]),
            ],
            'isActive' => ['required', new Enum(StatusEnum::class)],
            'password'=> [
                'required',
                // 'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'roleId'=> [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(RoleAssignmentService::class)->isAssignable($this->user(), $value)) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));
                    }
                },
            ],
            // 'avatar' => ["nullable","image", "mimes:jpeg,jpg,png,gif,svg,webp", "max:5120"],
          'mediaId' => ['nullable', 'integer'],
          'mediaFile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->mediaId && !$this->hasFile('mediaFile')) {
                $validator->errors()->add('media', __('crud.nullable_choice_upload'));
            }

            if ($this->mediaId && $this->hasFile('mediaFile')) {
                $validator->errors()->add('media',  __('crud.multi_choice_uploadmulti_choice_upload'));
            }
        });
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
            'email.unique' => __('validation.custom.unique'),
            'password.required' => __('validation.custom.required'),

        ];
    }

}
