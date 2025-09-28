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

class UpdateOrderRequest extends FormRequest
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
            // 'type' => ['nullable',new Enum(OrderTypeEnum::class)],
             'orderItems' => ['required', 'array', 'min:1'],
             'orderItems.*.productId' => ['required', 'integer', 'exists:products,id'],
             'orderItems.*.qty' => ['required', 'integer', 'min:1'],
             'orderItems.*.orderItemId' => [
                    'nullable',
                    'integer',
                    'required_if:orderItems.*.actionStatus,update,delete,""',
                    'exists:order_items,id',
             'orderItems.*.actionStatus' => ['required', 'in:update,delete,create,'],
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
            'name.required'=> __('validation.custom.required'),
            'type.required' => __('validation.custom.required'),
            'orderItems.required' => __('validation.custom.required'),
        ];
    }

}
