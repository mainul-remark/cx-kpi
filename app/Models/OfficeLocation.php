<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OfficeLocation extends Model
{
    protected $fillable = ['name', 'lat', 'lng', 'radius_m', 'allowed_ips', 'is_active'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'radius_m' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The allowed addresses as a clean list. */
    public function ipList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))));
    }
}
