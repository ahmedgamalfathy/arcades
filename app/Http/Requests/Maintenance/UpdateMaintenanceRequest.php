<?php

namespace App\Http\Requests\Maintenance;

use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class UpdateMaintenanceRequest extends FormRequest
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
            'deviceId' => ['required','exists:devices,id'],
            'price'=> ['required','integer','min:1'],
            'title' => ['required','string'],
            'place' => ['nullable','string'],
            'date'=>['required','string','date_format:Y-m-d'],
            'note'=>['nullable','string']
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
            'date.required' => __('validation.custom.required'),
            'price.required' => __('validation.custom.required'),
            'title.required' => __('validation.custom.required'),
        ];
    }

}
