<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SocialPlatform extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'active',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function dailyReportReplies(): HasMany
    {
        return $this->hasMany(DailyReportPlatformReply::class);
    }

    public function dailyTargetReplies(): HasMany
    {
        return $this->hasMany(DailyTargetPlatformReply::class);
    }

    /**
     * Create a new social platform, or update the given one, from validated data.
     */
    public static function createOrUpdateSocialPlatform(array $data, ?self $socialPlatform = null): self
    {
        $socialPlatform ??= new self();

        $socialPlatform->fill([
            'name'   => $data['name'],
            'notes'  => $data['notes'] ?? null,
            'active' => $data['active'] ?? true,
        ]);

        if ($socialPlatform->isDirty('name') || empty($socialPlatform->slug)) {
            $socialPlatform->slug = self::generateUniqueSlug($socialPlatform->name, $socialPlatform->id);
        }

        $socialPlatform->save();

        return $socialPlatform;
    }

    /**
     * Build a slug from the name, suffixing it when another platform already uses it.
     */
    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'platform';
        $slug = $base;
        $suffix = 1;

        while (self::where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
