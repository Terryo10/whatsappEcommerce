<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliverySlot extends Model
{
    protected $fillable = [
        'date',
        'label',
        'time_from',
        'time_to',
        'max_orders',
        'current_orders',
        'is_available',
    ];

    protected $casts = [
        'date' => 'date',
        'is_available' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasCapacity(): bool
    {
        return $this->is_available && $this->current_orders < $this->max_orders;
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->max_orders - $this->current_orders);
    }
}
