<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Driver;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeliveryFeeResult
{
    public function __construct(
        public readonly float $fee,
        public readonly string $currency,
        public readonly int $estimatedMinutes,
        public readonly string $zoneName,
        public readonly bool $available = true,
        public readonly bool $isFree = false,
    ) {}

    public static function unavailable(): self
    {
        return new self(0, 'USD', 0, '', available: false);
    }

    public static function free(string $zone, int $minutes): self
    {
        return new self(0, 'USD', $minutes, $zone, isFree: true);
    }
}

class DeliveryService
{
    public function calculateFee(string $suburb, float $orderTotal): DeliveryFeeResult
    {
        $zone = DeliveryZone::matchSuburb($suburb);

        if (!$zone) {
            return DeliveryFeeResult::unavailable();
        }

        if ($zone->free_delivery_above !== null && $orderTotal >= $zone->free_delivery_above) {
            return DeliveryFeeResult::free($zone->name, $zone->estimated_minutes);
        }

        $fee = (float) $zone->base_fee;

        return new DeliveryFeeResult(
            fee: round($fee, 2),
            currency: 'USD',
            estimatedMinutes: $zone->estimated_minutes,
            zoneName: $zone->name,
        );
    }

    public function calculateFeeAmount(DeliveryZone $zone, float $orderTotal): float
    {
        if ($zone->free_delivery_above !== null && $orderTotal >= $zone->free_delivery_above) {
            return 0.0;
        }
        return (float) $zone->base_fee;
    }

    public function getAvailableSlots(Carbon $date): Collection
    {
        return DeliverySlot::where('date', $date->toDateString())
            ->where('is_available', true)
            ->whereColumn('current_orders', '<', 'max_orders')
            ->orderBy('time_from')
            ->get();
    }

    public function assignDriver(Order $order, Driver $driver): Delivery
    {
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $order->update(['status' => 'assigned', 'driver_id' => $driver->user_id]);

        return $delivery;
    }

    public function generateTrackingLink(Order $order): string
    {
        if (!$order->tracking_token) {
            $order->update(['tracking_token' => Str::uuid()]);
            $order->refresh();
        }
        return url("/track/{$order->tracking_token}");
    }

    public function updateDriverLocation(Driver $driver, float $lat, float $lng): void
    {
        $driver->update([
            'current_lat' => $lat,
            'current_lng' => $lng,
            'last_location_update' => now(),
        ]);
    }

    public function getTrackingInfo(string $token): ?array
    {
        $order = Order::where('tracking_token', $token)
            ->with(['customer', 'items.product', 'deliverySlot', 'delivery.driver'])
            ->first();

        if (!$order) {
            return null;
        }

        $driver = $order->delivery?->driver;

        return [
            'order_reference' => $order->reference,
            'status' => $order->status,
            'customer_name' => $order->customer->name,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'qty' => $item->quantity,
                'price' => $item->unit_price,
            ]),
            'delivery_slot' => $order->deliverySlot?->label,
            'driver' => $driver ? [
                'name' => $driver->name,
                'vehicle' => $driver->vehicle_type . ($driver->vehicle_plate ? " ({$driver->vehicle_plate})" : ''),
                'lat' => $driver->current_lat,
                'lng' => $driver->current_lng,
                'last_update' => $driver->last_location_update?->toIso8601String(),
            ] : null,
            'estimated_arrival' => $order->delivery?->estimated_arrival_at?->toIso8601String(),
        ];
    }
}
