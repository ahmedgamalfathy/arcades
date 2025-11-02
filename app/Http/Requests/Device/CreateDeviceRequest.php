<?php

namespace App\Http\Requests\Device;

use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class CreateDeviceRequest extends FormRequest
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
          'mediaId' => ['nullable', 'integer', 'exists:media,id'],
          'mediaFile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
          'name' =>['required','string','unique:devices,name'],
          'deviceTypeId'=>['required','integer','exists:device_types,id'],
          'deviceTimeIds'=>['required','array'],
          'deviceTimeIds.*'=>['integer', 'exists:device_times,id'],
          'deviceTimeSpecial'=>['nullable','array'],
          'deviceTimeSpecial.*.name'=>['required','string'],
          'deviceTimeSpecial.*.rate'=>['required','numeric'],
        ];
    }
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->mediaId && !$this->hasFile('mediaFile')) {
                $validator->errors()->add('media', 'You must select an existing image or upload a new one.');
            }

            if ($this->mediaId && $this->hasFile('mediaFile')) {
                $validator->errors()->add('media', 'You cannot upload an image and select an existing image at the same time.');
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
            //name , price , date , note ,type
        return [
            'name.required' => __('validation.custom.required'),
            'media.required' => __('validation.custom.required'),
            'deviceTypeId.required' => __('validation.custom.required'),
            'deviceTimeIds.required' => __('validation.custom.required'),
        ];
    }

}
