<?php

namespace App\Http\Requests;

class StoreReportJobRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', 'max:100'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'user_id' => ['required', 'integer', 'min:1'],
            'force_fail' => ['sometimes', 'boolean'],
        ];
    }
}
