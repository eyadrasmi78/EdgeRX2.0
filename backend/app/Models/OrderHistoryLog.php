<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderHistoryLog extends Model
{
    protected $fillable = ['order_id', 'status', 'timestamp', 'note'];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
