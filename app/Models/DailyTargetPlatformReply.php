<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTargetPlatformReply extends Model
{
    protected $fillable = [
        'daily_target_id',
        'social_platform_id',
        'total_replies',
    ];

    protected function casts(): array
    {
        return [
            'total_replies' => 'integer',
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
