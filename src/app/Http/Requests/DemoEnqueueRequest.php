<?php

namespace App\Http\Requests;

class DemoEnqueueRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'count' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'force_fail' => ['sometimes', 'boolean'],
        ];
    }
}
