<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'active',
        'slug',
        'has_outbound_calls',
        'has_comments',
        'has_message_replies',
    ];

    protected function casts(): array
    {
        return [
            'active'                => 'boolean',
            'has_outbound_calls'    => 'boolean',
            'has_comments'          => 'boolean',
            'has_message_replies'   => 'boolean',
        ];
    }

    public function dailyReportCalls(): HasMany
    {
        return $this->hasMany(DailyReportProjectCall::class);
    }

    public function dailyTargetCalls(): HasMany
    {
        return $this->hasMany(DailyTargetProjectCall::class);
    }

    /**
     * Create a new project, or update the given one, from validated data.
     */
    public static function createOrUpdateProject(array $data, ?self $project = null): self
    {
        $project ??= new self();

        $project->fill([
            'name'                  => $data['name'],
            'notes'                 => $data['notes'] ?? null,
            'active'                => $data['active'] ?? true,
            'has_outbound_calls'    => $data['has_outbound_calls'] ?? true,
            'has_comments'          => $data['has_comments'] ?? true,
            'has_message_replies'   => $data['has_message_replies'] ?? true,
        ]);

        if ($project->isDirty('name') || empty($project->slug)) {
            $project->slug = self::generateUniqueSlug($project->name, $project->id);
        }

        $project->save();

        return $project;
    }

    /**
     * Build a slug from the name, suffixing it when another project already uses it.
     */
    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $suffix = 1;

        while (self::where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
