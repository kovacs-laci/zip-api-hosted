<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        if ($this->method() === 'PATCH' || $this->method() === 'PUT') {
            return [
                'county_id' => 'nullable',
                'name' => 'nullable',
            ];
        }
        return [
            'county_id' => 'required|exists:counties,id',
            'name' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'county_id.required' => __('validate_city.county_id.required'),
            'name.required' => __('validate_city.name.required'),
        ];
    }
}
