<?php

namespace App\Http\Requests\Order;

use App\Helpers\ApiResponse;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\DiscountType;
use App\Enums\Order\OrderTypeEnum;
use Illuminate\Validation\Rules\Enum;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class CreateOrderRequest extends FormRequest
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
            'name' => ['required','string'],
            'type' => ['required',new Enum(OrderTypeEnum::class)],
            'orderItems' => ['required', 'array', 'min:1'],
            'orderItems.*.productId' => ["required", 'integer', 'exists:products,id'],
            'orderItems.*.qty' => ['required', 'integer', 'min:1'],
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
            'name.required'=> __('validation.custom.required'),
            'type.required' => __('validation.custom.required'),
            'orderItems.required' => __('validation.custom.required'),
            'productId.required' => __('validation.custom.required'),
            'qty.required' => __('validation.custom.required'),
        ];
    }

}
