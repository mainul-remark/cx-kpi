<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DailyReport extends Model
{
    protected $fillable = [
        'user_id',
        'report_date',
        'outbound_calls',
        'outbound_calls_note',
        'inbound_calls',
        'inbound_calls_note',
        'order_processing',
        'order_processing_note',
    ];

    protected function casts(): array
    {
        return [
            'report_date'      => 'date:Y-m-d',
            'outbound_calls'   => 'integer',
            'inbound_calls'    => 'integer',
            'order_processing' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platformReplies(): HasMany
    {
        return $this->hasMany(DailyReportPlatformReply::class);
    }

    public function projectCalls(): HasMany
    {
        return $this->hasMany(DailyReportProjectCall::class);
    }

    /**
     * What each count of a project or platform row needs switched on for it to be taken.
     */
    private const ROW_FLAGS = [
        'inbound_calls'   => 'has_outbound_calls',
        'comments'        => 'has_comments',
        'message_replies' => 'has_message_replies',
    ];

    /**
     * Save a user's report from validated data, together with its platform and project rows.
     *
     * Without a report the user's report for the given date is created, or updated when
     * one already exists, so a user never ends up with two reports for the same day.
     * The date comes from the caller (a request never carries one), else from the data of a programmatic caller.
     */
    public static function saveForUser(User $user, array $data, ?self $report = null, ?string $date = null): self
    {
        return DB::transaction(function () use ($user, $data, $report, $date) {
            $date ??= $data['report_date'] ?? $report?->report_date?->toDateString() ?? today()->toDateString();

            $report ??= self::query()
                ->where('user_id', $user->id)
                ->whereDate('report_date', $date)
                ->first() ?? new self(['user_id' => $user->id]);

            $report->fill([
                'report_date'           => $date,
                'outbound_calls'        => $data['outbound_calls'],
                'outbound_calls_note'   => $data['outbound_calls_note'] ?? null,
                'inbound_calls'         => $data['inbound_calls'],
                'inbound_calls_note'    => $data['inbound_calls_note'] ?? null,
                'order_processing'      => $data['order_processing'] ?? 0,
                'order_processing_note' => $data['order_processing_note'] ?? null,
            ]);
            $report->save();

            $report->syncRows('platformReplies', 'social_platform_id', SocialPlatform::class, $data['platforms'] ?? []);
            $report->syncRows('projectCalls', 'project_id', Project::class, $data['projects'] ?? []);

            // the days the user forgot to check out on are ended at 11:59 pm, still marked as not checked out
            app(\App\Services\Attendance\AttendanceService::class)->finalizeForgotten($user);

            return $report->load(['platformReplies.socialPlatform', 'projectCalls.project']);
        });
    }

    /**
     * Update or create the child row of each submitted project or platform, leaving the others as they are.
     *
     * A count whose activity is switched off for the project or platform is left as it was, so it can't be written.
     *
     * @param  class-string<Model>  $entity
     */
    private function syncRows(string $relation, string $key, string $entity, array $rows): void
    {
        $flags = $entity::query()
            ->whereIn('id', array_column($rows, $key))
            ->get(['id', ...array_values(self::ROW_FLAGS)])
            ->keyBy('id');

        foreach ($rows as $row) {
            $values = ['note' => $row['note'] ?? null];

            foreach (self::ROW_FLAGS as $column => $flag) {
                if ($flags->get($row[$key])?->{$flag}) {
                    $values[$column] = $row[$column] ?? 0;
                }
            }

            // a fresh relation each time, as its query keeps the constraints of earlier calls
            $this->{$relation}()->updateOrCreate([$key => $row[$key]], $values);
        }
    }
}
