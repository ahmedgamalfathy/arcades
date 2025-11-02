<?php

namespace App\Http\Requests\Device\DevcieTime;

use App\Helpers\ApiResponse;
use Illuminate\Validation\Rule;
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
            'name' => [
                'required',
                'string',
                Rule::unique('device_times', 'name')
                    ->ignore($this->route('device_time')) // هنا بتستثني السجل الحالي
                    ->when($this->deviceTypeId, fn($query) =>
                        $query->where('device_type_id', $this->deviceTypeId)
                    )
                    ->when($this->deviceId, fn($query) =>
                        $query->where('device_id', $this->deviceId)
                    ),
            ],
            'rate'=> ['required','numeric','min:1'],
            'deviceTypeId' => [
                'nullable',
                'integer',
                'exists:device_types,id',
                'required_without:deviceId',
            ],
            'deviceId' => [
                'nullable',
                'integer',
                'exists:devices,id',
                'required_without:deviceTypeId',
            ],
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
