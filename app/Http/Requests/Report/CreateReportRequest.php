<?php

namespace App\Http\Requests\Report;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;



class CreateReportRequest extends FormRequest
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
            'startDateTime' => ['nullable', 'date', 'required_with:endDateTime'],
            'endDateTime'   => ['nullable', 'date', 'required_with:startDateTime', 'after_or_equal:startDateTime'],
            'include' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'dailyId' => ['nullable', 'integer', 'exists:dailies,id'],
        ];
    }

    public function failedValidation(Validator $validator)
    {

        throw new HttpResponseException(
            ApiResponse::error('', $validator->errors(), HttpStatusCode::UNPROCESSABLE_ENTITY)
        );
    }



}
