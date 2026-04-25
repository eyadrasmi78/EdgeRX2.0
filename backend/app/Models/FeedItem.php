<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FeedItem extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'type', 'title', 'description', 'timestamp',
        'author_id', 'author_name', 'author_role',
        'is_pinned', 'expiry_date', 'metadata',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'expiry_date' => 'datetime',
        'is_pinned' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (FeedItem $f) {
            if (empty($f->id)) $f->id = (string) Str::uuid();
            if (empty($f->timestamp)) $f->timestamp = now();
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
