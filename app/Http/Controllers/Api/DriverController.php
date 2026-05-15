<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(private DeliveryService $service) {}

    public function deliveries(Request $request): JsonResponse
    {
        $driver = Driver::where('user_id', $request->user()->id)->firstOrFail();
        $deliveries = Delivery::where('driver_id', $driver->id)
            ->with(['order.customer', 'order.deliveryAddress'])
            ->latest()
            ->get();

        return response()->json($deliveries);
    }

    public function show(Delivery $delivery): JsonResponse
    {
        return response()->json($delivery->load(['order.items.product', 'order.customer', 'order.deliveryAddress']));
    }

    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $request->validate(['status' => 'required|in:picked_up,in_transit,delivered,failed']);

        $updates = ['status' => $request->status];
        if ($request->status === 'picked_up') $updates['picked_up_at'] = now();
        if ($request->status === 'delivered') $updates['delivered_at'] = now();

        $delivery->update($updates);

        // Map delivery status to order status
        $orderStatus = match ($request->status) {
            'picked_up', 'in_transit' => 'in_transit',
            'delivered' => 'delivered',
            default => $delivery->order->status,
        };
        $delivery->order->update(['status' => $orderStatus]);

        return response()->json(['message' => 'Status updated', 'delivery' => $delivery->fresh()]);
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate(['lat' => 'required|numeric', 'lng' => 'required|numeric']);
        $driver = Driver::where('user_id', $request->user()->id)->firstOrFail();
        $this->service->updateDriverLocation($driver, (float) $request->lat, (float) $request->lng);

        return response()->json(['message' => 'Location updated']);
    }

    public function uploadPhoto(Request $request, Delivery $delivery): JsonResponse
    {
        $request->validate(['photo' => 'required|image|max:5120']);
        $path = $request->file('photo')->store('delivery-photos', 'public');
        $delivery->update(['delivery_photo_url' => $path]);

        return response()->json(['message' => 'Photo uploaded', 'url' => asset('storage/' . $path)]);
    }
}
