<?php

namespace App\Http\Requests\Order;

use App\Helpers\ApiResponse;
use Illuminate\Validation\Rule;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\DiscountType;
use App\Enums\Order\OrderTypeEnum;
use Illuminate\Validation\Rules\Enum;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Order\Order;
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
        //  ['string',  'min:3', Rule::unique('users', 'email')->ignore($this->route('user'))]
        return [
            'name' => ['string',
            // Rule::unique('orders', 'name')->ignore($this->route('order')),
                Rule::requiredIf(function () {
                $order = Order::find($this->route('order'));
                return $order && $order->booked_device_id == null;
                }),
            ],
             'orderItems' => ['required', 'array', 'min:1'],
             'orderItems.*.productId' => ['required', 'integer', 'exists:products,id'],
             'orderItems.*.qty' => ['required', 'integer', 'min:1'],
            'orderItems.*.orderItemId' => [
            'nullable',
            'integer',
            'required_if:orderItems.*.actionStatus,update,delete,""',
            'exists:order_items,id',
            function ($attribute, $value, $fail) {
                if ($value) {
                    $orderId = $this->route('order'); // id بتاع الأوردر الحالي
                    $orderItem = \App\Models\Order\OrderItem::find($value);
                    if ($orderItem && $orderItem->order_id != $orderId) {
                        $fail("The order item ID does not belong to this order.");
                    }
                }
            },
            ],
            'orderItems.*.actionStatus' => ['required', 'in:update,delete,create,""'],
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
            'orderItems.required' => __('validation.custom.required'),
        ];
    }

}
