<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSession extends Model
{
    public const REASON_MANUAL = 'manual';
    public const REASON_AUTO = 'auto';
    public const REASON_ADMIN = 'admin';

    protected $fillable = [
        'user_id',
        'work_date',
        'checked_in_at',
        'checked_out_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_accuracy',
        'check_out_lat',
        'check_out_lng',
        'check_out_accuracy',
        'check_in_ip',
        'check_out_ip',
        'check_in_device',
        'check_out_device',
        'close_reason',
        'auto_closed_at',
        'acknowledged_at',
        'adjusted_by',
        'adjustment_note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'auto_closed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /** Sessions the user is still inside of: neither checked out nor closed by the system. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('checked_out_at')->whereNull('auto_closed_at');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('work_date', $date);
    }

    /** Closed by the system because the user never checked out. */
    public function scopeForgotten(Builder $query): Builder
    {
        return $query->where('close_reason', self::REASON_AUTO);
    }

    public function isAutoClosed(): bool
    {
        return $this->close_reason === self::REASON_AUTO;
    }
}
