<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTargetPlatformReply extends Model
{
    protected $fillable = [
        'daily_target_id',
        'social_platform_id',
        'inbound_calls',
        'comments',
        'message_replies',
    ];

    protected function casts(): array
    {
        return [
            'inbound_calls'   => 'integer',
            'comments'        => 'integer',
            'message_replies' => 'integer',
        ];
    }

    public function dailyTarget(): BelongsTo
    {
        return $this->belongsTo(DailyTarget::class);
    }

    public function socialPlatform(): BelongsTo
    {
        return $this->belongsTo(SocialPlatform::class);
    }
}
