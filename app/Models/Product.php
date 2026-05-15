<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price_usd',
        'price_zwl',
        'image_url',
        'unit',
        'stock_qty',
        'low_stock_threshold',
        'whatsapp_retailer_id',
        'whatsapp_sync_status',
        'whatsapp_synced_at',
        'whatsapp_sync_error',
        'is_active',
        'is_whatsapp_visible',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
        'price_zwl' => 'decimal:2',
        'is_active' => 'boolean',
        'is_whatsapp_visible' => 'boolean',
        'whatsapp_synced_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(CatalogSyncLog::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_qty <= $this->low_stock_threshold;
    }
}
