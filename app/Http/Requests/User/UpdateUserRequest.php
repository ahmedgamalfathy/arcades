<?php

namespace App\Http\Requests\User;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Enums\StatusEnum;
use App\Helpers\ApiResponse;
use App\Models\User;
use App\Services\User\RoleAssignmentService;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return User::query()
            ->managedBy($this->user())
            ->whereKey($this->route('user'))
            ->exists();
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('crud.not_found'), [], HttpStatusCode::NOT_FOUND)
        );
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
            'email' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('mysql.users', 'email')->ignore($this->route('user')),
            ],
            'mediaId' => ['nullable', 'integer'],
            'mediaFile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'phone' => ['nullable', 'numeric'],
            'address' => 'nullable',
            'isActive' => ['required', new Enum(StatusEnum::class)],
            'roleId' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(RoleAssignmentService::class)->isAssignable($this->user(), $value)) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));
                    }
                },
            ],
            'password' => [
                'nullable',
                // 'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,svg,webp', 'max:5120'], // , "max:2048"
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->isMethod('POST')) {
                if (! $this->mediaId && ! $this->hasFile('mediaFile')) {
                    $validator->errors()->add('media', 'You must select an existing image or upload a new one.');
                }
            }

            if ($this->mediaId && $this->hasFile('mediaFile')) {
                $validator->errors()->add('media', 'You cannot upload an image and select an existing image at the same time.');
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
            'password.required' => __('validation.custom.required'),
        ];
    }
}
