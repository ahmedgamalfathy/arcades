<?php

namespace App\Http\Requests\Expense;

use App\Helpers\ApiResponse;
use Illuminate\Validation\Rules\Enum;
use App\Enums\Expense\ExpenseTypeEnum;
use App\Services\Expense\ExpenseService;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class UpdateExpenseRequest extends FormRequest
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
            'price'=> ['required','integer','min:1'],
            'type' => ['required', new Enum(ExpenseTypeEnum::class)],
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
            'type.required' => __('validation.custom.required'),
        ];
    }

}
