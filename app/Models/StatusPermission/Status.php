<?php

namespace App\Models\StatusPermission;

use App\Observers\StatusPermission\StatusObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[ObservedBy(StatusObserver::class)]
class Status extends Model
{
    use HasFactory;

    protected $table = 'statuses';

    protected $fillable = [
        'slug',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function active(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)->orderBy('label')->get();
    }

    /**
     * Returns the ACL action name for this status.
     * slug "processing" → "changeToProcessing"
     */
    public function aclAction(): string
    {
        return 'changeTo' . Str::studly($this->slug);
    }
}
