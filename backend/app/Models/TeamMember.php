<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TeamMember extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'parent_user_id', 'name', 'email', 'phone',
        'job_title', 'password', 'permissions',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'permissions' => 'array',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::creating(function (TeamMember $m) {
            if (empty($m->id)) $m->id = (string) Str::uuid();
        });
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }
}
