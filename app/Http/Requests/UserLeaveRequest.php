<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use App\Models\UserLeave;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserLeaveRequest extends FormRequest
{
    /**
     * The longest range, in days, a leave can be set for in one go.
     */
    public const MAX_RANGE_DAYS = 366;

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
     * A new leave covers users over a date range, an existing one is a single day of one user.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $details = [
            'portion' => ['required', Rule::in(array_keys(UserLeave::PORTIONS))],
            'type'    => ['required', Rule::in(array_keys(UserLeave::TYPES))],
            'note'    => ['nullable', 'string', 'max:5000'],
        ];

        if ($leave = $this->route('leave')) {
            return $details + [
                'leave_date' => [
                    'required',
                    'date_format:Y-m-d',
                    function (string $attribute, mixed $value, \Closure $fail) use ($leave) {
                        if (empty(Holiday::workingDays($value, $value))) {
                            $fail('This date is a Friday or a holiday, which is an off day already.');

                            return;
                        }

                        $taken = UserLeave::query()
                            ->where('user_id', $leave->user_id)
                            ->whereDate('leave_date', $value)
                            ->whereKeyNot($leave->id)
                            ->exists();

                        if ($taken) {
                            $fail('This user already has a leave on this date.');
                        }
                    },
                ],
            ];
        }

        return $details + [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('usages_sector', 'field')],

            'from' => ['required', 'date_format:Y-m-d'],
            'to'   => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $from = $this->input('from');

                    if (is_string($from) && Carbon::hasFormat($from, 'Y-m-d')
                        && Carbon::parse($from)->diffInDays(Carbon::parse($value)) >= self::MAX_RANGE_DAYS) {
                        $fail('The date range cannot be longer than '.self::MAX_RANGE_DAYS.' days.');
                    }
                },
            ],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // a half day is still worked, so only a full day collides with a report
                if ($validator->errors()->isNotEmpty() || $this->input('portion') !== UserLeave::PORTION_FULL) {
                    return;
                }

                $leave = $this->route('leave');
                $field = $leave ? 'leave_date' : 'to';
                $userIds = $leave ? [$leave->user_id] : array_map('intval', $this->input('user_ids'));
                $dates = $leave
                    ? [$this->input('leave_date')]
                    : Holiday::workingDays($this->input('from'), $this->input('to'));

                $reports = UserLeave::reportsOn($userIds, $dates);

                if (!empty($reports)) {
                    $shown = array_slice($reports, 0, 5);
                    $more = count($reports) - count($shown);

                    $validator->errors()->add(
                        $field,
                        'A full day of leave cannot be set on a day already reported on: '.implode(', ', $shown)
                        .($more > 0 ? ' and '.$more.' more' : '').'. Use a half day, or delete the report first.'
                    );
                }
            },
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
            'user_ids'   => 'field user',
            'user_ids.*' => 'field user',
            'from'       => 'from date',
            'to'         => 'to date',
            'leave_date' => 'date',
            'portion'    => 'leave duration',
            'type'       => 'leave type',
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
            'user_ids.required' => 'Select at least one field user.',
            'user_ids.*.exists' => 'The selected user is not a field user.',
        ];
    }
}
