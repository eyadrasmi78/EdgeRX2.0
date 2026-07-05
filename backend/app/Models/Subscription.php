<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'account_id', 'module_key', 'billing_period', 'status',
        'unit_price_kd', 'current_period_start', 'current_period_end', 'auto_renew',
    ];

    protected $casts = [
        'unit_price_kd'        => 'decimal:2',
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'auto_renew'           => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_key', 'key');
    }
}
