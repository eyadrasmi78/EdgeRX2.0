<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key', 'name', 'role_scope', 'monthly_price_kd', 'is_core', 'sort_order',
    ];

    protected $casts = [
        'monthly_price_kd' => 'decimal:2',
        'is_core'          => 'boolean',
        'sort_order'       => 'integer',
    ];
}
