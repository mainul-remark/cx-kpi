<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\SocialPlatform;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DailyTargetRequest extends FormRequest
{
    /**
     * The longest range, in days, a target can be set for in one go.
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // an empty count means no target for that activity
        $count = ['nullable', 'integer', 'min:0', 'max:4294967295'];

        return [
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

            'outbound_calls'  => $count,
            'inbound_calls'   => $count,
            'message_replies' => $count,

            'platforms'                      => ['nullable', 'array'],
            'platforms.*.social_platform_id' => ['required', 'integer', 'distinct', Rule::in(SocialPlatform::query()->where('active', true)->pluck('id')->all())],
            'platforms.*.total_replies'      => $count,

            'projects'               => ['nullable', 'array'],
            'projects.*.project_id'  => ['required', 'integer', 'distinct', Rule::in(Project::query()->where('active', true)->pluck('id')->all())],
            'projects.*.total_calls' => $count,
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

                $counts = collect($this->only(['outbound_calls', 'inbound_calls', 'message_replies']))
                    ->merge(collect($this->input('platforms', []))->pluck('total_replies'))
                    ->merge(collect($this->input('projects', []))->pluck('total_calls'));

                if ($counts->every(fn ($value) => $value === null || $value === '')) {
                    $validator->errors()->add('outbound_calls', 'Set a target for at least one activity.');
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
            'user_ids'                       => 'field user',
            'user_ids.*'                     => 'field user',
            'from'                           => 'from date',
            'to'                             => 'to date',
            'platforms.*.social_platform_id' => 'social platform',
            'platforms.*.total_replies'      => 'comment replies',
            'projects.*.project_id'          => 'project',
            'projects.*.total_calls'         => 'total calls',
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
            'user_ids.required'                 => 'Select at least one field user.',
            'user_ids.*.exists'                 => 'The selected user is not a field user.',
            'platforms.*.social_platform_id.in' => 'The selected social platform is not active.',
            'projects.*.project_id.in'          => 'The selected project is not active.',
        ];
    }
}
