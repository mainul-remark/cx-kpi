<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload an import file.',
            'file.mimes'    => 'The import file must be xlsx, xls, or csv.',
            'file.max'      => 'The import file must not exceed 5MB.',
        ];
    }
}
