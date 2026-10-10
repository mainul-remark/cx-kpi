<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportProjectCall extends Model
{
    protected $fillable = [
        'daily_report_id',
        'project_id',
        'inbound_calls',
        'comments',
        'message_replies',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'inbound_calls'   => 'integer',
            'comments'        => 'integer',
            'message_replies' => 'integer',
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
