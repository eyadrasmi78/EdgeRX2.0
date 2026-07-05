<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountModuleEntitlement extends Model
{
    protected $fillable = [
        'account_id', 'module_key', 'source', 'active', 'activated_at', 'expires_at',
    ];

    protected $casts = [
        'active'       => 'boolean',
        'activated_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];
}
