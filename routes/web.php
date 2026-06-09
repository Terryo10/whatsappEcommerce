<?php

use App\Http\Controllers\WhatsApp\WebhookController;
use App\Models\Product;
use App\Services\Delivery\DeliveryService;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// WhatsApp Webhook (no auth - Meta signature verification inside controller)
Route::prefix('webhook')->group(function () {
    Route::get('/whatsapp', [WebhookController::class, 'verify'])->name('webhook.whatsapp.verify');
    Route::post('/whatsapp', [WebhookController::class, 'handle'])->name('webhook.whatsapp');
});

// Public order tracking (no auth)
Route::get('/track/{token}', function (string $token, DeliveryService $service) {
    $info = $service->getTrackingInfo($token);
    if (!$info) {
        abort(404, 'Tracking token not found.');
    }
    return response()->json($info);
})->name('track.order');

// Public product page used in WhatsApp catalog URLs
Route::get('/products/{slug}', function (string $slug) {
    $product = Product::query()
        ->with('category')
        ->where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

    return view('product-show', [
        'product' => $product,
        'imageUrl' => $product->image_url
            ? (str_starts_with($product->image_url, 'http://') || str_starts_with($product->image_url, 'https://')
                ? $product->image_url
                : asset('storage/' . ltrim($product->image_url, '/')))
            : null,
    ]);
})->name('products.show');
