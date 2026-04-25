<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    protected $primaryKey = 'order_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['order_id'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'order_id', 'order_id')->orderBy('timestamp');
    }
}
