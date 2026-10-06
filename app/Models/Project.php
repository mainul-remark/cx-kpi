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
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
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
            'name'   => $data['name'],
            'notes'  => $data['notes'] ?? null,
            'active' => $data['active'] ?? true,
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
