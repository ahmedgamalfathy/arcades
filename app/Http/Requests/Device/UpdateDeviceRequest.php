<?php

namespace App\Http\Requests\Device;

use App\Helpers\ApiResponse;
use Illuminate\Validation\Rule;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class UpdateDeviceRequest extends FormRequest
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
          'mediaId' => ['nullable', 'integer', 'exists:media,id'],
          'mediaFile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
          'name' => [
            'required',
            'string',
            Rule::unique('devices', 'name')->ignore($this->route('device')),
        ],
          'deviceTypeId'=>['required','integer','exists:device_types,id'],
          'deviceTimeIds'=>['required','array'],
          'deviceTimeIds.*'=>['integer', 'exists:device_times,id'],
        'deviceTimeSpecial' => [
                'nullable',
                'array',
                'required_without:deviceTimeIds',
                'min:1'
            ],
            'deviceTimeSpecial.*.name' => [
                'required_if:deviceTimeSpecial.*.actionStatus,create',
                'string'
            ],

            'deviceTimeSpecial.*.rate' => [
                'required_if:deviceTimeSpecial.*.actionStatus,create',
                'numeric'
            ],
            'deviceTimeSpecial.*.actionStatus' => [
                'required',
                Rule::in(['create', 'update', 'delete']),
            ],
            'deviceTimeSpecial.*.timeTypeId' => [
                'required_if:deviceTimeSpecial.*.actionStatus,update,delete',
                'integer',
                'exists:device_times,id'
            ],

        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->mediaId && $this->hasFile('mediaFile')) {
                $validator->errors()->add('media','You cannot upload an image and select an existing image at the same time.');
            }
        });
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
            'media.required' => __('validation.custom.required'),
            'deviceTypeId.required' => __('validation.custom.required'),
            'deviceTimeIds.required' => __('validation.custom.required'),
        ];
    }

}
