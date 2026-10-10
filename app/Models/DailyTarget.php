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
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'target_date'    => 'date:Y-m-d',
            'outbound_calls' => 'integer',
            'inbound_calls'  => 'integer',
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
                        'user_id'        => $userId,
                        'target_date'    => $date,
                        'outbound_calls' => $data['outbound_calls'] ?? null,
                        'inbound_calls'  => $data['inbound_calls'] ?? null,
                        'set_by'         => $setBy,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            foreach (array_chunk($targets, 500) as $chunk) {
                self::query()->upsert(
                    $chunk,
                    ['user_id', 'target_date'],
                    ['outbound_calls', 'inbound_calls', 'set_by', 'updated_at']
                );
            }

            $targetIds = self::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('target_date', $dates)
                ->pluck('id');

            self::replaceRows(new DailyTargetPlatformReply(), $targetIds->all(), 'social_platform_id', $data['platforms'] ?? []);
            self::replaceRows(new DailyTargetProjectCall(), $targetIds->all(), 'project_id', $data['projects'] ?? []);
        });
    }

    /**
     * The approximate targets a project or platform row can hold. They inform, the KPI does not count them.
     */
    private const ROW_COUNTS = ['inbound_calls', 'comments', 'message_replies'];

    /**
     * Swap the child rows of the given targets for the submitted ones, skipping rows left without any count.
     */
    private static function replaceRows(Model $model, array $targetIds, string $key, array $rows): void
    {
        $now = now();
        $rows = array_filter($rows, fn (array $row) => collect(self::ROW_COUNTS)
            ->contains(fn (string $column) => ($row[$column] ?? null) !== null && $row[$column] !== ''));

        foreach (array_chunk($targetIds, 500) as $chunk) {
            $model->newQuery()->whereIn('daily_target_id', $chunk)->delete();

            $inserts = [];
            foreach ($chunk as $targetId) {
                foreach ($rows as $row) {
                    $insert = [
                        'daily_target_id' => $targetId,
                        $key              => $row[$key],
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];

                    foreach (self::ROW_COUNTS as $column) {
                        $insert[$column] = ($row[$column] ?? '') === '' ? null : $row[$column];
                    }

                    $inserts[] = $insert;
                }
            }

            foreach (array_chunk($inserts, 1000) as $insertChunk) {
                $model->newQuery()->insert($insertChunk);
            }
        }
    }
}
