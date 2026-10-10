<?php

namespace App\Http\Requests;

use App\Models\DailyReport;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\UserLeave;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DailyReportRequest extends FormRequest
{
    private ?string $reportDate = null;

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
     * The report date is not an input: the system picks it, see reportDate().
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
        // the counts of a project or a platform are left out of the form when their activity is switched off
        $optionalCount = ['nullable', 'integer', 'min:0', 'max:4294967295'];
        $note = ['nullable', 'string', 'max:5000'];

        return [
            'outbound_calls'        => $count,
            'outbound_calls_note'   => $note,
            'inbound_calls'         => $count,
            'inbound_calls_note'    => $note,
            'order_processing'      => $count,
            'order_processing_note' => $note,

            'platforms'                      => ['nullable', 'array'],
            'platforms.*.social_platform_id' => ['required', 'integer', 'distinct', Rule::in($platformIds)],
            'platforms.*.inbound_calls'      => $optionalCount,
            'platforms.*.comments'           => $optionalCount,
            'platforms.*.message_replies'    => $optionalCount,
            'platforms.*.note'               => $note,

            'projects'                    => ['nullable', 'array'],
            'projects.*.project_id'       => ['required', 'integer', 'distinct', Rule::in($projectIds)],
            'projects.*.inbound_calls'    => $optionalCount,
            'projects.*.comments'         => $optionalCount,
            'projects.*.message_replies'  => $optionalCount,
            'projects.*.note'             => $note,
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // a half day of leave is still worked, a full day is not
                $onLeave = UserLeave::query()
                    ->where('user_id', $this->user()->id)
                    ->whereDate('leave_date', $this->reportDate())
                    ->where('portion', UserLeave::PORTION_FULL)
                    ->where('status', UserLeave::STATUS_APPROVED)
                    ->exists();

                if ($onLeave) {
                    $validator->errors()->add('outbound_calls', 'You are on leave on this date, so no report can be submitted for it.');
                }
            },
        ];
    }

    /**
     * The day this report is for, picked by the system: the report's own day on update, else today.
     */
    public function reportDate(): string
    {
        return $this->reportDate ??= $this->route('daily_report')?->report_date->toDateString()
            ?? today()->toDateString();
    }

    /**
     * The report this request writes to: the routed one on update, the user's report for the day on store.
     */
    private function existingReport(): ?DailyReport
    {
        if ($this->route('daily_report')) {
            return $this->route('daily_report');
        }

        return DailyReport::query()
            ->where('user_id', $this->user()->id)
            ->whereDate('report_date', $this->reportDate())
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
            'platforms.*.social_platform_id' => 'social platform',
            'platforms.*.inbound_calls'      => 'outbound calls',
            'platforms.*.comments'           => 'comments',
            'platforms.*.message_replies'    => 'message replies',
            'platforms.*.note'               => 'note',
            'projects.*.project_id'          => 'project',
            'projects.*.inbound_calls'       => 'outbound calls',
            'projects.*.comments'            => 'comments',
            'projects.*.message_replies'     => 'message replies',
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
            'platforms.*.social_platform_id.in' => 'The selected social platform is not active.',
            'projects.*.project_id.in'          => 'The selected project is not active.',
        ];
    }
}
