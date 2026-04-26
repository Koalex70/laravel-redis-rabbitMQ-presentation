<?php

namespace App\Http\Requests;

use App\Support\ApiErrorResponder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        $response = ApiErrorResponder::respond(
            'validation_error',
            'Validation failed.',
            422,
            $validator->errors()->toArray(),
        );

        throw new ValidationException($validator, $response);
    }
}
