<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\SocialPlatform;
use App\Services\Dashboard\ReportDashboardService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The KPI sheet as a workbook in the company format: one tab per user, named after their employee id,
 * with a line per activity that had a target and its weighted score.
 */
class EmployeeKpiExport implements WithMultipleSheets
{
    /**
     * The characters a tab name cannot hold, and how long it can be.
     */
    private const TAB_FORBIDDEN = ['\\', '/', '?', '*', '[', ']', ':'];

    private const TAB_MAX_LENGTH = 31;

    /**
     * @param  list<array<string, mixed>>  $rows  the rows of the KPI sheet, with their targets
     * @param  string|null  $preset  the period chosen on the KPI page, nothing for a range typed in
     */
    public function __construct(private array $rows, private string $from, private string $to, private ?string $preset = null)
    {
    }

    /**
     * @return list<EmployeeKpiUserSheet>
     */
    public function sheets(): array
    {
        $frequency = self::frequency($this->from, $this->to, $this->preset);
        $labels = $this->activityLabels();
        $taken = [];
        $sheets = [];

        // in the order of the employee ids, so a user is found where they are expected
        $rows = collect($this->rows)->sortBy(fn (array $row) => mb_strtolower((string) ($row['employee_id'] ?: $row['name'])))->values();

        foreach ($rows as $row) {
            $sheets[] = new EmployeeKpiUserSheet($this->tabName($row, $taken), $row, $this->lines($row, $labels), $frequency);
        }

        // a workbook cannot be saved without a tab
        return $sheets ?: [new EmployeeKpiUserSheet('KPI', null, [], $frequency)];
    }

    /**
     * How often the period comes round: what the chosen period is called, or what its length says for a custom range.
     *
     * A month in progress is still monthly, however few of its days have passed.
     */
    public static function frequency(string $from, string $to, ?string $preset = null): string
    {
        $named = ['today' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'];

        if (isset($named[$preset])) {
            return $named[$preset];
        }

        $days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        return match (true) {
            $days <= 1 => 'Daily',
            $days <= 7 => 'Weekly',
            $days <= 31 => 'Monthly',
            default => 'Yearly',
        };
    }

    /**
     * The lines of a user: an activity that had a target, with the share of the score it carries as weight.
     *
     * The weights are worked out by the KPI service, the outbound calls at their configured share (or all of it when
     * nothing else has a target) and the rest split equally. An admin can change them on the sheet afterwards.
     * An activity counts for no more than its weight however far above target it went.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $labels
     * @return list<array{title: string, target: int, actual: int, achievement: float, weight: float, score: float}>
     */
    private function lines(array $row, array $labels): array
    {
        $order = array_flip(array_keys($labels));

        return collect($row['lines'] ?? [])
            ->map(fn (array $line, string $key) => [
                'title' => $labels[$key] ?? $key,
                'target' => $line['target'],
                'actual' => $line['actual'],
                'achievement' => $line['achievement'],
                'weight' => $line['weight'],
                'score' => round($line['score'], 2),
                'order' => $order[$key] ?? PHP_INT_MAX,
            ])
            ->sortBy('order')
            ->map(fn (array $line) => array_diff_key($line, ['order' => true]))
            ->values()
            ->all();
    }

    /**
     * What each activity is called on the sheet, in the order the lines are shown.
     *
     * @return array<string, string>
     */
    private function activityLabels(): array
    {
        $keys = collect($this->rows)->flatMap(fn (array $row) => array_keys($row['lines'] ?? []))->unique();
        $ids = fn (string $prefix) => $keys->filter(fn (string $key) => str_starts_with($key, $prefix))->map(fn (string $key) => (int) substr($key, strlen($prefix)));

        $labels = [];
        foreach (['outbound_calls', 'inbound_calls'] as $metric) {
            $labels[$metric] = ReportDashboardService::METRICS[$metric];
        }

        $counts = ['inbound_calls' => 'Inbound Calls', 'comments' => 'Comments', 'message_replies' => 'Message Replies'];

        $platforms = SocialPlatform::query()->whereIn('id', $ids('platform:'))->orderBy('name')->get(['id', 'name']);
        $projects = Project::query()->whereIn('id', $ids('project:'))->orderBy('name')->get(['id', 'name']);

        foreach (['platform:' => $platforms, 'project:' => $projects] as $prefix => $entities) {
            foreach ($entities as $entity) {
                foreach ($counts as $column => $title) {
                    $labels[$prefix.$entity->id.':'.$column] = $entity->name.' ('.$title.')';
                }
            }
        }

        return $labels;
    }

    /**
     * The tab of a user: their employee id, or their name when the id is missing, made fit for a tab name and unlike the others.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $taken  the tab names used so far, in lower case
     */
    private function tabName(array $row, array &$taken): string
    {
        $clean = fn (?string $value) => trim(str_replace(self::TAB_FORBIDDEN, ' ', (string) $value), " '");

        $base = $clean($row['employee_id']) ?: ($clean($row['name']) ?: 'User '.$row['id']);
        $name = mb_substr($base, 0, self::TAB_MAX_LENGTH);

        for ($copy = 2; isset($taken[mb_strtolower($name)]); $copy++) {
            $suffix = ' ('.$copy.')';
            $name = mb_substr($base, 0, self::TAB_MAX_LENGTH - mb_strlen($suffix)).$suffix;
        }

        $taken[mb_strtolower($name)] = true;

        return $name;
    }
}
