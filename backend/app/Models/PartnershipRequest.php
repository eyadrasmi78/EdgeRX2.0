<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PartnershipRequest extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'from_agent_id', 'from_agent_name',
        'to_foreign_supplier_id',
        'status', 'date', 'message',
        'product_id', 'product_name', 'request_type',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PartnershipRequest $r) {
            if (empty($r->id)) $r->id = (string) Str::uuid();
            if (empty($r->date)) $r->date = now();
        });
    }

    public function fromAgent()
    {
        return $this->belongsTo(User::class, 'from_agent_id');
    }

    public function toForeignSupplier()
    {
        return $this->belongsTo(User::class, 'to_foreign_supplier_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
