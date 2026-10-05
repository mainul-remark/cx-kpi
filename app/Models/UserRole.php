<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'user_roles';
    protected $primaryKey = null;
    protected $fillable = ['user_id', 'role_id'];

    public function role()
    {
        return $this->hasOne(Role::class, 'role_id', 'role_id');
    }
}
