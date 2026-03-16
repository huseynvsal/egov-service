<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResidenceInfoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fin' => ['required', 'string', 'min:5', 'max:7', 'alpha_num'],
        ];
    }
}
