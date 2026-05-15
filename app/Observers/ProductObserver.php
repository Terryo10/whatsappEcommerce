<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\WhatsApp\CatalogSyncService;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    public function saved(Product $product): void
    {
        if (!$product->is_whatsapp_visible || !$product->is_active) {
            return;
        }

        // Only sync if relevant fields changed
        $syncFields = ['name', 'description', 'price_usd', 'image_url', 'stock_qty', 'is_active', 'is_whatsapp_visible'];
        $changed = array_intersect($syncFields, array_keys($product->getChanges()));

        if (empty($changed) && $product->whatsapp_sync_status === 'synced') {
            return;
        }

        $product->update(['whatsapp_sync_status' => 'pending']);

        try {
            app(CatalogSyncService::class)->syncProduct($product);
        } catch (\Throwable $e) {
            Log::warning("Product observer sync failed for {$product->id}", ['error' => $e->getMessage()]);
        }
    }

    public function deleted(Product $product): void
    {
        if ($product->whatsapp_retailer_id) {
            try {
                app(CatalogSyncService::class)->deleteProduct($product);
            } catch (\Throwable $e) {
                Log::warning("Product observer delete sync failed for {$product->id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
