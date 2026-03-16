<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonalInfoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fin'       => ['required', 'string', 'size:7', 'alpha_num'],
            'docNumber' => ['nullable', 'string', 'alpha_num'],
        ];
    }
}
