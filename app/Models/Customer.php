<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'whatsapp_phone',
        'preferred_payment_method',
        'conversation_state',
    ];

    protected $casts = [
        'conversation_state' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(DeliveryAddress::class);
    }

    public function defaultAddress(): ?DeliveryAddress
    {
        return $this->addresses()->where('is_default', true)->first();
    }
}
