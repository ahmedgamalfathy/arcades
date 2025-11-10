<?php

namespace App\Http\Requests\Product;

use App\Helpers\ApiResponse;
use Illuminate\Validation\Rule;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



class UpdateProductRequest extends FormRequest
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
            'name' => ['string','required',Rule::unique('products')->ignore($this->route('product'))],
            'price'=> ['required','numeric','min:1'],
            // 'status' => ['nullable', new Enum(StatusEnum::class)],
            'path' => ["nullable","image", "mimes:jpeg,jpg,png,gif,svg,webp", "max:5120"],
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
            'path.required' => __('validation.custom.required'),
            'name.required' => __('validation.custom.required'),
            'price.required' => __('validation.custom.required'),
        ];
    }

}
