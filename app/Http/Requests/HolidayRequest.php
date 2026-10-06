<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
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
            'holiday_date' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $taken = Holiday::query()
                        ->whereDate('holiday_date', $value)
                        ->when($this->route('holiday'), fn ($query, $holiday) => $query->whereKeyNot($holiday->id))
                        ->exists();

                    if ($taken) {
                        $fail('A holiday is already set for this date.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }
}
