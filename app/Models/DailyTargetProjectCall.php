<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTargetProjectCall extends Model
{
    protected $fillable = [
        'daily_target_id',
        'project_id',
        'total_calls',
    ];

    protected function casts(): array
    {
        return [
            'total_calls' => 'integer',
        ];
    }

    public function dailyTarget(): BelongsTo
    {
        return $this->belongsTo(DailyTarget::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
