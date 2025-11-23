<?php

namespace App\Http\Requests\User;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateUserProfileRequest extends FormRequest
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
            // 'email'=> [
            //     'required',
            //     'email',
            //     'unique:users,email,' . $this->route('user'),
            // ],
          'mediaId' => ['nullable', 'integer'],
          'mediaFile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }
     public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // if (!$this->mediaId && !$this->hasFile('mediaFile')) {
            //     $validator->errors()->add('media', 'You must select an existing image or upload a new one.');
            // }

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
