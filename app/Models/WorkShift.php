<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    protected $fillable = ['name', 'start_time', 'grace_minutes'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
