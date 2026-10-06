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
        'message_replies',
        'message_replies_note',
    ];

    protected function casts(): array
    {
        return [
            'report_date'     => 'date:Y-m-d',
            'outbound_calls'  => 'integer',
            'inbound_calls'   => 'integer',
            'message_replies' => 'integer',
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
     * Save a user's report from validated data, together with its platform and project rows.
     *
     * Without a report the user's report for the given date is created, or updated when
     * one already exists, so a user never ends up with two reports for the same day.
     */
    public static function saveForUser(User $user, array $data, ?self $report = null): self
    {
        return DB::transaction(function () use ($user, $data, $report) {
            $report ??= self::query()
                ->where('user_id', $user->id)
                ->whereDate('report_date', $data['report_date'])
                ->first() ?? new self(['user_id' => $user->id]);

            $report->fill([
                'report_date'          => $data['report_date'],
                'outbound_calls'       => $data['outbound_calls'],
                'outbound_calls_note'  => $data['outbound_calls_note'] ?? null,
                'inbound_calls'        => $data['inbound_calls'],
                'inbound_calls_note'   => $data['inbound_calls_note'] ?? null,
                'message_replies'      => $data['message_replies'],
                'message_replies_note' => $data['message_replies_note'] ?? null,
            ]);
            $report->save();

            $report->syncRows('platformReplies', 'social_platform_id', 'total_replies', $data['platforms'] ?? []);
            $report->syncRows('projectCalls', 'project_id', 'total_calls', $data['projects'] ?? []);

            return $report->load(['platformReplies.socialPlatform', 'projectCalls.project']);
        });
    }

    /**
     * Make the child rows match the submitted ones: update or create each, drop the rest.
     */
    private function syncRows(string $relation, string $key, string $countColumn, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            // a fresh relation each time, as its query keeps the constraints of earlier calls
            $this->{$relation}()->updateOrCreate(
                [$key => $row[$key]],
                [$countColumn => $row[$countColumn], 'note' => $row['note'] ?? null]
            );
            $keptIds[] = $row[$key];
        }

        $this->{$relation}()->whereNotIn($key, $keptIds)->delete();
    }
}
