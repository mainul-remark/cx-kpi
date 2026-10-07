<?php

namespace App\Http\Requests;

use App\Models\DailyReport;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\UserLeave;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // a report can only be changed by the user it belongs to
        $report = $this->route('daily_report');

        return !$report || (int) $report->user_id === (int) $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $report = $this->existingReport();

        // inactive projects and platforms stay valid on a report that already holds a row for them
        $projectIds = Project::query()->where('active', true)->pluck('id')
            ->merge($report?->projectCalls()->pluck('project_id') ?? [])
            ->all();
        $platformIds = SocialPlatform::query()->where('active', true)->pluck('id')
            ->merge($report?->platformReplies()->pluck('social_platform_id') ?? [])
            ->all();

        $count = ['required', 'integer', 'min:0', 'max:4294967295'];
        $note = ['nullable', 'string', 'max:5000'];

        return [
            'report_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                // store saves over the report of the same day, so only an update can collide
                function (string $attribute, mixed $value, \Closure $fail) use ($report) {
                    $taken = DailyReport::query()
                        ->where('user_id', $this->user()->id)
                        ->whereDate('report_date', $value)
                        ->when($report, fn ($query) => $query->whereKeyNot($report->id))
                        ->exists();

                    if ($taken) {
                        $fail('You already have a report for this date.');
                    }
                },
                // a half day of leave is still worked, a full day is not
                function (string $attribute, mixed $value, \Closure $fail) {
                    $onLeave = UserLeave::query()
                        ->where('user_id', $this->user()->id)
                        ->whereDate('leave_date', $value)
                        ->where('portion', UserLeave::PORTION_FULL)
                        ->where('status', UserLeave::STATUS_APPROVED)
                        ->exists();

                    if ($onLeave) {
                        $fail('You are on leave on this date, so no report can be submitted for it.');
                    }
                },
            ],
            'outbound_calls'       => $count,
            'outbound_calls_note'  => $note,
            'inbound_calls'        => $count,
            'inbound_calls_note'   => $note,
            'message_replies'      => $count,
            'message_replies_note' => $note,

            'platforms'                      => ['nullable', 'array'],
            'platforms.*.social_platform_id' => ['required', 'integer', 'distinct', Rule::in($platformIds)],
            'platforms.*.total_replies'      => $count,
            'platforms.*.note'               => $note,

            'projects'               => ['nullable', 'array'],
            'projects.*.project_id'  => ['required', 'integer', 'distinct', Rule::in($projectIds)],
            'projects.*.total_calls' => $count,
            'projects.*.note'        => $note,
        ];
    }

    /**
     * The report this request writes to: the routed one on update, the user's report for the date on store.
     */
    private function existingReport(): ?DailyReport
    {
        if ($this->route('daily_report')) {
            return $this->route('daily_report');
        }

        $date = $this->input('report_date');
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return DailyReport::query()
            ->where('user_id', $this->user()->id)
            ->whereDate('report_date', $date)
            ->first();
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'report_date'                    => 'report date',
            'platforms.*.social_platform_id' => 'social platform',
            'platforms.*.total_replies'      => 'comment replies',
            'platforms.*.note'               => 'note',
            'projects.*.project_id'          => 'project',
            'projects.*.total_calls'         => 'total calls',
            'projects.*.note'                => 'note',
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
            'report_date.before_or_equal'       => 'The report date cannot be in the future.',
            'platforms.*.social_platform_id.in' => 'The selected social platform is not active.',
            'projects.*.project_id.in'          => 'The selected project is not active.',
        ];
    }
}
