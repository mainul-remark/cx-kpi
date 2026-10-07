<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use App\Models\UserLeave;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeaveRequestRequest extends FormRequest
{
    /**
     * The longest range, in days, a user can ask leave for in one go.
     */
    public const MAX_RANGE_DAYS = 31;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // leave is kept for the users who report, which are the field users
        return $this->user()->usages_sector === 'field';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'portion' => ['required', Rule::in(array_keys(UserLeave::PORTIONS))],
            'type'    => ['required', Rule::in(array_keys(UserLeave::TYPES))],
            'note'    => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $userId = (int) $this->user()->id;
                $dates = Holiday::workingDays($this->input('from'), $this->input('to'));

                if (empty($dates)) {
                    $validator->errors()->add('to', 'The selected range has no working day. Fridays and holidays are off days already.');

                    return;
                }

                // a leave already approved is not put back to pending by asking again
                $approved = UserLeave::query()
                    ->where('user_id', $userId)
                    ->where('status', UserLeave::STATUS_APPROVED)
                    ->whereBetween('leave_date', [min($dates), max($dates).' 23:59:59'])
                    ->orderBy('leave_date')
                    ->get(['leave_date'])
                    ->toBase()
                    ->map(fn (UserLeave $leave) => $leave->leave_date->toDateString())
                    ->intersect($dates)
                    ->values();

                if ($approved->isNotEmpty()) {
                    $validator->errors()->add('to', 'You already have approved leave on '.$approved->take(5)->implode(', ')
                        .($approved->count() > 5 ? ' and '.($approved->count() - 5).' more' : '').'.');

                    return;
                }

                // a half day is still worked, so only a full day collides with a report
                if ($this->input('portion') === UserLeave::PORTION_FULL && !empty(UserLeave::reportsOn([$userId], $dates))) {
                    $validator->errors()->add('to', 'A full day of leave cannot be asked for a day you already reported on. Ask for a half day instead.');
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
            'from'    => 'from date',
            'to'      => 'to date',
            'portion' => 'leave duration',
            'type'    => 'leave type',
        ];
    }
}
