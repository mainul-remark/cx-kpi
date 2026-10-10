<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportPlatformReply extends Model
{
    protected $fillable = [
        'daily_report_id',
        'social_platform_id',
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

    public function socialPlatform(): BelongsTo
    {
        return $this->belongsTo(SocialPlatform::class);
    }
}
