<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostcodeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        if ($this->method() === 'PATCH' || $this->method() === 'PUT') {
            return [
                'city_id' => 'sometimes|required|integer|exists:cities,id',
                'postcode' => 'sometimes|required|string|max:4',
            ];
        }

        return [
            'city_id' => 'required|integer|exists:cities,id',
            'postcode' => 'required|string|max:4',
        ];
    }

    public function messages()
    {
        return [
            'city_id.required' => __('validate_postcode.city_id.required'),
            'postcode.required' => __('validate_postcode.postcode.required'),
        ];
    }
}
