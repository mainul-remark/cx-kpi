<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HolidayImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:xlsx,xls,csv',
                // a csv file is plain text as far as its content tells
                'mimes:xlsx,xls,csv,txt',
                'max:5120',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required'   => 'Please upload an import file.',
            'file.extensions' => 'The import file must be xlsx, xls, or csv.',
            'file.mimes'      => 'The import file must be xlsx, xls, or csv.',
            'file.max'        => 'The import file must not exceed 5MB.',
        ];
    }
}
