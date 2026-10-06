<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DailyTarget extends Model
{
    protected $fillable = [
        'user_id',
        'target_date',
        'outbound_calls',
        'inbound_calls',
        'message_replies',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'target_date'     => 'date:Y-m-d',
            'outbound_calls'  => 'integer',
            'inbound_calls'   => 'integer',
            'message_replies' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function platformReplies(): HasMany
    {
        return $this->hasMany(DailyTargetPlatformReply::class);
    }

    public function projectCalls(): HasMany
    {
        return $this->hasMany(DailyTargetProjectCall::class);
    }

    /**
     * Give every listed user the same target on every listed day, from validated data.
     *
     * A target already set for a user on one of the days is replaced as a whole,
     * so an activity left empty ends up without a target on those days.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $dates  dates as Y-m-d
     */
    public static function setForUsers(array $userIds, array $dates, array $data, ?int $setBy = null): void
    {
        DB::transaction(function () use ($userIds, $dates, $data, $setBy) {
            $now = now();
            $targets = [];

            foreach ($userIds as $userId) {
                foreach ($dates as $date) {
                    $targets[] = [
                        'user_id'         => $userId,
                        'target_date'     => $date,
                        'outbound_calls'  => $data['outbound_calls'] ?? null,
                        'inbound_calls'   => $data['inbound_calls'] ?? null,
                        'message_replies' => $data['message_replies'] ?? null,
                        'set_by'          => $setBy,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }
            }

            foreach (array_chunk($targets, 500) as $chunk) {
                self::query()->upsert(
                    $chunk,
                    ['user_id', 'target_date'],
                    ['outbound_calls', 'inbound_calls', 'message_replies', 'set_by', 'updated_at']
                );
            }

            $targetIds = self::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('target_date', $dates)
                ->pluck('id');

            self::replaceRows(new DailyTargetPlatformReply(), $targetIds->all(), 'social_platform_id', 'total_replies', $data['platforms'] ?? []);
            self::replaceRows(new DailyTargetProjectCall(), $targetIds->all(), 'project_id', 'total_calls', $data['projects'] ?? []);
        });
    }

    /**
     * Swap the child rows of the given targets for the submitted ones, skipping rows left without a count.
     */
    private static function replaceRows(Model $model, array $targetIds, string $key, string $countColumn, array $rows): void
    {
        $now = now();
        $rows = array_filter($rows, fn (array $row) => ($row[$countColumn] ?? null) !== null);

        foreach (array_chunk($targetIds, 500) as $chunk) {
            $model->newQuery()->whereIn('daily_target_id', $chunk)->delete();

            $inserts = [];
            foreach ($chunk as $targetId) {
                foreach ($rows as $row) {
                    $inserts[] = [
                        'daily_target_id' => $targetId,
                        $key              => $row[$key],
                        $countColumn      => $row[$countColumn],
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }
            }

            foreach (array_chunk($inserts, 1000) as $insertChunk) {
                $model->newQuery()->insert($insertChunk);
            }
        }
    }
}
