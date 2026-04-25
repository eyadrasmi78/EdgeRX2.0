<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'order_id', 'sender_id', 'sender_name', 'text', 'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatMessage $m) {
            if (empty($m->id)) $m->id = (string) Str::uuid();
            if (empty($m->timestamp)) $m->timestamp = now();
        });
    }

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'order_id', 'order_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
