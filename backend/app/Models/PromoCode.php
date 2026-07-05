<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code', 'customer_id', 'module_keys', 'waiver_days',
        'max_redemptions', 'redeemed_count', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'module_keys'     => 'array',
        'waiver_days'     => 'integer',
        'max_redemptions' => 'integer',
        'redeemed_count'  => 'integer',
        'expires_at'      => 'datetime',
    ];

    public function redemptions()
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function isRedeemable(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return $this->redeemed_count < $this->max_redemptions;
    }
}
