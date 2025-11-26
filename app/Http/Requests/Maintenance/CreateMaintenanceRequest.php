<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\Expense\ExpenseTypeEnum;
use App\Helpers\ApiResponse;
use Illuminate\Validation\Rules\Enum;

use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class CreateMaintenanceRequest extends FormRequest
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
    {//device_id , title , price , date ,note
        return [
            'deviceId' => ['required','exists:devices,id'],
            'price'=> ['required','numeric','min:1'],
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
            //name , price , date , note ,type
        return [
            'name.required' => __('validation.custom.required'),
            'date.required' => __('validation.custom.required'),
            'price.required' => __('validation.custom.required'),
            'title.required' => __('validation.custom.required'),
        ];
    }

}
