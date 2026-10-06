<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportProjectCall extends Model
{
    protected $fillable = [
        'daily_report_id',
        'project_id',
        'total_calls',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'total_calls' => 'integer',
        ];
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
