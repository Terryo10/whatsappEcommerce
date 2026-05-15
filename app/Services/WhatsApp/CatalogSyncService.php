<?php

namespace App\Services\WhatsApp;

use App\Models\CatalogSyncLog;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CatalogSyncService
{
    private string $accessToken;
    private string $catalogId;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct()
    {
        $this->accessToken = config('services.meta.access_token', '');
        $this->catalogId = config('services.meta.catalog_id', '');
        $this->apiVersion = config('services.meta.api_version', 'v21.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    public function syncProduct(Product $product): void
    {
        $action = $product->whatsapp_retailer_id ? 'update' : 'create';
        $payload = $this->buildProductPayload($product);

        $log = CatalogSyncLog::create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'action' => $action,
            'status' => 'pending',
            'request_payload' => $payload,
        ]);

        try {
            if (empty($this->accessToken) || empty($this->catalogId)) {
                throw new \RuntimeException('Meta API credentials not configured. Set META_ACCESS_TOKEN and META_CATALOG_ID in .env');
            }

            $response = Http::withToken($this->accessToken)
                ->post("{$this->baseUrl}/{$this->catalogId}/products", $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $retailerId = $responseData['id'] ?? $product->whatsapp_retailer_id ?? $payload['retailer_id'];

                $product->update([
                    'whatsapp_retailer_id' => $retailerId,
                    'whatsapp_sync_status' => 'synced',
                    'whatsapp_synced_at' => now(),
                    'whatsapp_sync_error' => null,
                ]);

                $log->update(['status' => 'success', 'response_payload' => $responseData]);
            } else {
                $error = $response->json('error.message', 'Unknown error');
                throw new \RuntimeException($error);
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp catalog sync failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);

            $product->update([
                'whatsapp_sync_status' => 'failed',
                'whatsapp_sync_error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deleteProduct(Product $product): void
    {
        if (!$product->whatsapp_retailer_id) {
            return;
        }

        $log = CatalogSyncLog::create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'action' => 'delete',
            'status' => 'pending',
        ]);

        try {
            if (empty($this->accessToken) || empty($this->catalogId)) {
                throw new \RuntimeException('Meta API credentials not configured.');
            }

            $response = Http::withToken($this->accessToken)
                ->delete("{$this->baseUrl}/{$this->catalogId}/products", [
                    'retailer_id' => $product->whatsapp_retailer_id,
                ]);

            if ($response->successful()) {
                $product->update(['whatsapp_retailer_id' => null, 'whatsapp_sync_status' => 'not_synced']);
                $log->update(['status' => 'success']);
            } else {
                throw new \RuntimeException($response->json('error.message', 'Delete failed'));
            }
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function bulkSync(): void
    {
        $products = Product::where('is_active', true)->where('is_whatsapp_visible', true)->get();

        CatalogSyncLog::create([
            'product_name' => "Bulk sync ({$products->count()} products)",
            'action' => 'bulk_sync',
            'status' => 'pending',
        ]);

        foreach ($products as $product) {
            try {
                $this->syncProduct($product);
            } catch (\Throwable $e) {
                Log::warning("Bulk sync: failed for product {$product->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    public function updateAvailability(Product $product): void
    {
        if (!$product->whatsapp_retailer_id) {
            return;
        }

        $availability = ($product->is_active && $product->stock_qty > 0) ? 'in stock' : 'out of stock';

        try {
            Http::withToken($this->accessToken)
                ->post("{$this->baseUrl}/{$this->catalogId}/products", [
                    'retailer_id' => $product->whatsapp_retailer_id,
                    'availability' => $availability,
                ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to update availability for product {$product->id}", ['error' => $e->getMessage()]);
        }
    }

    private function buildProductPayload(Product $product): array
    {
        $retailerId = $product->whatsapp_retailer_id ?? $product->slug;

        return [
            'retailer_id' => $retailerId,
            'name' => $product->name,
            'description' => $product->description ?? $product->name,
            'price' => (int) ($product->price_usd * 100), // in cents
            'currency' => 'USD',
            'availability' => ($product->is_active && $product->stock_qty > 0) ? 'in stock' : 'out of stock',
            'condition' => 'new',
            'image_url' => $product->image_url ? url('storage/' . $product->image_url) : null,
            'url' => url('/products/' . $product->slug),
            'category' => $product->category?->name ?? 'Groceries',
        ];
    }
}
