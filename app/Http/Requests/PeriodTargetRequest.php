<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\SocialPlatform;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PeriodTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // an empty count means no target for that activity, only the outbound call target is required
        $count = ['nullable', 'integer', 'min:0', 'max:4294967295'];

        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('usages_sector', 'field')],

            'month' => ['required', 'date_format:Y-m'],

            'outbound_calls' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'inbound_calls'  => $count,

            'platforms'                      => ['nullable', 'array'],
            'platforms.*.social_platform_id' => ['required', 'integer', 'distinct', Rule::in(SocialPlatform::query()->where('active', true)->pluck('id')->all())],
            'platforms.*.inbound_calls'      => $count,
            'platforms.*.comments'           => $count,
            'platforms.*.message_replies'    => $count,

            'projects'                   => ['nullable', 'array'],
            'projects.*.project_id'      => ['required', 'integer', 'distinct', Rule::in(Project::query()->where('active', true)->pluck('id')->all())],
            'projects.*.inbound_calls'   => $count,
            'projects.*.comments'        => $count,
            'projects.*.message_replies' => $count,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_ids'       => 'field user',
            'user_ids.*'     => 'field user',
            'outbound_calls' => 'outbound call target',
        ];
    }

    /**
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

    /**
     * The validated targets as the activity keys the KPI works with.
     *
     * @return array<string, int|null>
     */
    public function activities(): array
    {
        $data = $this->validated();
        $activities = [
            'outbound_calls' => $data['outbound_calls'],
            'inbound_calls'  => $data['inbound_calls'] ?? null,
        ];

        foreach (['platform' => ['platforms', 'social_platform_id'], 'project' => ['projects', 'project_id']] as $prefix => [$group, $key]) {
            foreach ($data[$group] ?? [] as $row) {
                foreach (['inbound_calls', 'comments', 'message_replies'] as $column) {
                    $activities[$prefix.':'.$row[$key].':'.$column] = $row[$column] ?? null;
                }
            }
        }

        return $activities;
    }
}
