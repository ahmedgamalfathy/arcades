<?php

namespace App\Http\Requests\Device\DevcieType;

use Illuminate\Support\Arr;
use App\Helpers\ApiResponse;
use App\Enums\ActionStatusEnum;
use App\Rules\UniqueDeviceTimeName;
use Illuminate\Validation\Rules\Enum;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class UpdateDeviceTypeRequest extends FormRequest
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
            'name' => ['string','required','unique:device_types,name,'.$this->route('id')],
            'times'=> ['required','array','min:1'],
            'times.*.name' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        $index = explode('.', $attribute)[1];
                        $time   = $this->input("times.$index", []);
                        $timeId = $time['timeTypeId'] ?? null;
                        $deviceTypeId = (int)$this->route('id');
                        (new UniqueDeviceTimeName($deviceTypeId, $timeId))
                            ->validate($attribute, $value, $fail);
                    },
                ],
            'times.*.rate'=>['required','integer','min:1'],
            'times.*.actionStatus'=> ['required',new Enum(ActionStatusEnum::class)],
            'times.*.timeTypeId'=> [ 'nullable', 'exists:device_times,id'],
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
            'times.required' => __('validation.custom.required'),
            'times.*.name.required' => __('validation.custom.required'),
            'times.*.rate.required' => __('validation.custom.required'),
            'times.*.actionStatus.required' => __('validation.custom.required'),
        ];
    }

}
