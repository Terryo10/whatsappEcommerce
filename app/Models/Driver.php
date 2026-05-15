<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'vehicle_type',
        'vehicle_plate',
        'is_active',
        'is_available',
        'current_lat',
        'current_lng',
        'last_location_update',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'last_location_update' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function activeDelivery(): ?Delivery
    {
        return $this->deliveries()->whereIn('status', ['assigned', 'picked_up', 'in_transit'])->latest()->first();
    }
}
