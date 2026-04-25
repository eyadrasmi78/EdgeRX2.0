<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'email', 'password', 'phone', 'role', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $u) {
            if (empty($u->id)) {
                $u->id = (string) Str::uuid();
            }
        });
    }

    public function companyDetails()
    {
        return $this->hasOne(CompanyDetails::class, 'user_id');
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class, 'parent_user_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    public function customerOrders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function supplierOrders()
    {
        return $this->hasMany(Order::class, 'supplier_id');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /* ---------- role helpers ---------- */
    public function isAdmin(): bool { return $this->role === 'ADMIN'; }
    public function isCustomer(): bool { return $this->role === 'CUSTOMER'; }
    public function isSupplier(): bool { return in_array($this->role, ['SUPPLIER', 'FOREIGN_SUPPLIER'], true); }
    public function isLocalSupplier(): bool { return $this->role === 'SUPPLIER'; }
    public function isForeignSupplier(): bool { return $this->role === 'FOREIGN_SUPPLIER'; }
    public function isApproved(): bool { return $this->status === 'APPROVED'; }
}
