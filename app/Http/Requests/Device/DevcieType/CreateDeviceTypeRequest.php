<?php

namespace App\Http\Requests\Device\DevcieType;

use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class CreateDeviceTypeRequest extends FormRequest
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
    {//name , rate , device_type_id
        return [
            'name' => ['string','required','unique:device_types,name'],
            'times'=> ['required','array','min:1'],
           'times.*.name' => [
            'required',
            'string',
            function ($attribute, $value, $fail) {
                $names = array_column($this->input('times'), 'name');

                if (count(array_keys($names, $value)) > 1) {
                    $fail("The name '$value' is duplicated within the request.");
                }
            }
        ],
            'times.*.rate'=>['required','numeric','min:1'],
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
            //name , price , date , note ,type
        return [
            'name.required' => __('validation.custom.required'),
            'name.unique' => __('validation.custom.unique'),
            'rate.required' => __('validation.custom.required'),
            'deviceTypeId.required' => __('validation.custom.required'),
        ];
    }

}
