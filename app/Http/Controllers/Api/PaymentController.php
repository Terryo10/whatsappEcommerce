<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function initiateEcoCash(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'phone' => 'required|string',
        ]);

        $order = Order::findOrFail($request->order_id);
        $order->update([
            'payment_method' => 'ecocash',
            'ecocash_number' => $request->phone,
        ]);

        // TODO: Integrate Paynow SDK
        Log::info("EcoCash payment initiated for order {$order->reference}", ['phone' => $request->phone]);

        return response()->json(['message' => 'Payment request sent. Please check your phone.', 'order_id' => $order->id]);
    }

    public function cardLink(Request $request): JsonResponse
    {
        $request->validate(['order_id' => 'required|exists:orders,id']);
        $order = Order::findOrFail($request->order_id);
        $order->update(['payment_method' => 'card']);

        $payLink = url("/pay/{$order->tracking_token}");
        return response()->json(['redirect_url' => $payLink]);
    }

    public function status(Order $order): JsonResponse
    {
        return response()->json([
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'order_status' => $order->status,
        ]);
    }
}
