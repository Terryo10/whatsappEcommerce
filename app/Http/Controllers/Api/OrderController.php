<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_address_id' => 'nullable|exists:delivery_addresses,id',
            'delivery_slot_id' => 'nullable|exists:delivery_slots,id',
            'notes' => 'nullable|string',
        ]);

        $subtotal = 0;
        $items = [];
        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $itemSubtotal = $product->price_usd * $item['quantity'];
            $subtotal += $itemSubtotal;
            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price_usd,
                'quantity' => $item['quantity'],
                'subtotal' => $itemSubtotal,
            ];
        }

        $order = Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $request->user()->id, // TODO: use customer model
            'channel' => 'web',
            'status' => 'pending_payment',
            'subtotal' => $subtotal,
            'delivery_fee' => 0,
            'total' => $subtotal,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'delivery_address_id' => $validated['delivery_address_id'] ?? null,
            'delivery_slot_id' => $validated['delivery_slot_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'tracking_token' => Str::uuid(),
        ]);

        foreach ($items as $item) {
            OrderItem::create(array_merge(['order_id' => $order->id], $item));
        }

        return response()->json($order->load('items'), 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['items.product', 'deliverySlot', 'deliveryAddress']));
    }

    public function history(Request $request): JsonResponse
    {
        // In a real app, filter by authenticated customer
        $orders = Order::latest()->paginate(15);
        return response()->json($orders);
    }
}
