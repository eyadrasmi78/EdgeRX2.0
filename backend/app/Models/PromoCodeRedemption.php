<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCodeRedemption extends Model
{
    protected $fillable = ['promo_code_id', 'account_id', 'redeemed_at'];

    protected $casts = ['redeemed_at' => 'datetime'];
}
