<?php

namespace App\Http\Requests\Timer\Individual;

use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class CreateIndividualRequest extends FormRequest
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
    {//name , price , date , note ,type
        return [
            'deviceId' => 'required|exists:devices,id',
            'deviceTypeId' => 'required|exists:device_types,id',
            'deviceTimeId' => 'required|exists:device_times,id',
            'startDateTime' => 'required|date',
            'endDateTime' => 'nullable|date|after:startDateTime',
            'dailyId' => 'required|exists:dailies,id',
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
            'deviceId.required' => __('validation.custom.required'),
            'deviceTypeId.required' => __('validation.custom.required'),
            'deviceTimeId.required' => __('validation.custom.required'),
            'startDateTime.required' => __('validation.custom.required'),
            'dailyId.required' => __('validation.custom.required'),
        ];
    }

}
