<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\WhatsApp\CatalogSyncService;
use Illuminate\Console\Command;

class SyncCatalog extends Command
{
    protected $signature = 'catalog:sync';
    protected $description = 'Sync all active products to the WhatsApp catalog';

    public function handle(CatalogSyncService $service): void
    {
        $total = Product::where('is_active', true)->where('is_whatsapp_visible', true)->count();
        $this->info("Syncing {$total} products to WhatsApp catalog...");

        $success = 0;
        $failed = 0;

        Product::where('is_active', true)
            ->where('is_whatsapp_visible', true)
            ->chunk(1, function ($products) use ($service, &$success, &$failed) {
                $product = $products->first();
                try {
                    $service->syncProduct($product);
                    $this->line("  ✓ {$product->name}");
                    $success++;
                } catch (\Throwable $e) {
                    $this->error("  ✗ {$product->name}: {$e->getMessage()}");
                    $failed++;
                }
                gc_collect_cycles();
            });

        $this->newLine();
        $this->info("Done — {$success} synced, {$failed} failed.");
    }
}
