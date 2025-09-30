<?php

namespace App\Http\Requests\Device\DevcieTime;

use App\Helpers\ApiResponse;
use Illuminate\Validation\Rules\Enum;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Services\Expense\ExpenseService;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class UpdateDeviceTimeRequest extends FormRequest
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
            'name' => ['string','required'],
            'rate'=> ['required','integer','min:1'],
            'deviceTypeId'=>['required','integer','exists:device_types,id'],
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
            'rate.required' => __('validation.custom.required'),
            'deviceTypeId.required' => __('validation.custom.required'),
        ];
    }

}
