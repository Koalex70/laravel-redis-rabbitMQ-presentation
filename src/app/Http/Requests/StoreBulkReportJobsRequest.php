<?php

namespace App\Http\Requests;

class StoreBulkReportJobsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:500'],
            'payload' => ['required', 'array'],
            'payload.report_type' => ['required', 'string', 'max:100'],
            'payload.from' => ['required', 'date'],
            'payload.to' => ['required', 'date', 'after_or_equal:payload.from'],
            'payload.user_id' => ['required', 'integer', 'min:1'],
            'payload.force_fail' => ['sometimes', 'boolean'],
        ];
    }
}
