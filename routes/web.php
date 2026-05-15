<?php

use App\Http\Controllers\WhatsApp\WebhookController;
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
