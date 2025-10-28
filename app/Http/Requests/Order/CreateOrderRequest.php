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
            'name' => [
                'string',
                'unique:orders,name',
                'required_if:type,1',
            ],
            'type' => ['required',new Enum(OrderTypeEnum::class)],
            // 'bookedDeviceId' => [
            //     'nullable',
            //     'exists:booked_devices,id',
            //     'required_if:type,0',
            // ],
            'orderItems' => ['required', 'array', 'min:1'],
            'orderItems.*.productId' => ["required", 'integer', 'exists:products,id'],
            'orderItems.*.qty' => ['required', 'integer', 'min:1'],
            'dailyId' => ['required_if:type,0', 'exists:dailies,id'],
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
            'name.required_if' => __('validation.custom.required'),
            'type.required' => __('validation.custom.required'),
            'bookedDeviceId.required_if' => __('validation.custom.required'),
            'orderItems.required' => __('validation.custom.required'),
            'productId.required' => __('validation.custom.required'),
            'qty.required' => __('validation.custom.required'),
        ];
    }

}
