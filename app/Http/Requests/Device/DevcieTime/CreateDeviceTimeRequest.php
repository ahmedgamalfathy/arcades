<?php

namespace App\Http\Requests\Device\DevcieTime;

use App\Helpers\ApiResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

use App\Enums\Expense\ExpenseTypeEnum;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class CreateDeviceTimeRequest extends FormRequest
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
            'deviceTypeId'=>['required','integer','exists:device_types,id'],
            'name' =>[
                'required',
                'string',
                Rule::unique('device_times', 'name')
                    ->where(fn ($query) =>
                        $query->where('device_type_id', request('deviceTypeId'))
                    ),
           ],
          'rate'=> ['required','integer','min:1'],
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
            'rate.required' => __('validation.custom.required'),
            'deviceTypeId.required' => __('validation.custom.required'),
        ];
    }

}
