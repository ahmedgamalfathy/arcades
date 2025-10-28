<?php

namespace App\Http\Requests\Daily;


use App\Helpers\ApiResponse;
use Illuminate\Validation\Rules\Enum;

use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class UpdateDailyRequest extends FormRequest
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
         'startDateTime'=> ['required','date_format:Y-m-d H:i:s'],
         'endDateTime'=> ['nullable','date_format:Y-m-d H:i:s'],
        //  'totalIncome'=> ['nullable','numeric','min:1'],
        //  'totalExpense'=> ['nullable','numeric','min:1'],
        //  'totalProfit'=> ['nullable','numeric','min:1'],
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
            'startDateTime.required' => __('validation.custom.required'),
        ];
    }

}
