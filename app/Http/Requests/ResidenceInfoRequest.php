<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResidenceInfoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fin' => ['required', 'string', 'min:5', 'max:7', 'alpha_num'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' => $validator->errors()->first(),
            'data'    => null,
        ], 422));
    }
}
