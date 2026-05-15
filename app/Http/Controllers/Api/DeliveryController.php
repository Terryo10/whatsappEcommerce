<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $service) {}

    public function slots(Request $request): JsonResponse
    {
        $date = $request->date ? \Carbon\Carbon::parse($request->date) : today();
        $slots = $this->service->getAvailableSlots($date);
        return response()->json($slots);
    }

    public function calculateFee(Request $request): JsonResponse
    {
        $request->validate(['suburb' => 'required|string', 'order_total' => 'required|numeric']);
        $result = $this->service->calculateFee($request->suburb, (float) $request->order_total);

        if (!$result->available) {
            return response()->json(['error' => 'Delivery not available for this suburb'], 422);
        }

        return response()->json([
            'fee' => $result->fee,
            'currency' => $result->currency,
            'estimated_minutes' => $result->estimatedMinutes,
            'zone_name' => $result->zoneName,
            'is_free' => $result->isFree,
        ]);
    }
}
