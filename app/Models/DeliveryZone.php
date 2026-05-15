<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = [
        'name',
        'suburb_keywords',
        'base_fee',
        'per_km_fee',
        'free_delivery_above',
        'estimated_minutes',
        'hub_lat',
        'hub_lng',
        'is_active',
    ];

    protected $casts = [
        'suburb_keywords' => 'array',
        'base_fee' => 'decimal:2',
        'per_km_fee' => 'decimal:2',
        'free_delivery_above' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function matchSuburb(string $suburb): ?self
    {
        $suburb = strtolower(trim($suburb));

        return static::where('is_active', true)->get()->first(function (self $zone) use ($suburb) {
            foreach ($zone->suburb_keywords as $keyword) {
                if (str_contains($suburb, strtolower($keyword)) || str_contains(strtolower($keyword), $suburb)) {
                    return true;
                }
            }
            return false;
        });
    }
}
