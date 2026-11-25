<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CountyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        if ($this->method() === 'PATCH' || $this->method() === 'PUT') {
            return [
                'name' => 'nullable',
            ];
        }
        return [
            'name' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => __('validate_county.name.required'),
        ];
    }
}
