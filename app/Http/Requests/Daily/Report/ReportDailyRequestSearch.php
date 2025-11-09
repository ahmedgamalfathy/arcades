<?php
namespace App\Http\Requests\Daily\Report;
use Illuminate\Foundation\Http\FormRequest;

class ReportDailyRequestSearch extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'dailyId' => 'required|exists:dailies,id',
            'search' => 'nullable',
            'include' => 'nullable',
        ];
    }
}
