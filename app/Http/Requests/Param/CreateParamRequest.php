<?php

namespace App\Http\Requests\Param;

use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateParamRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string|unique:params,type',
            'note' => 'nullable|string|max:255',
            'parameterOrder'=>'required|integer|min:1|exists:parameters,order'
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
            'type.unique' => __('validation.custom.unique'),
            'type.required' => __('validation.custom.required')
        ];
    }
}
