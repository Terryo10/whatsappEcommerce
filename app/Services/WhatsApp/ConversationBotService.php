<?php

namespace App\Services\WhatsApp;

use App\Models\Customer;
use App\Models\DeliveryAddress;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Delivery\DeliveryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationBotService
{
    public function __construct(
        private WhatsAppMessagingService $messenger,
        private DeliveryService $deliveryService,
    ) {}

    // ── Entry point: WhatsApp sends us an order from the native catalog ──────
    public function handleOrderMessage(string $phone, array $orderPayload): void
    {
        $customer = $this->findOrCreateCustomer($phone);

        // Build order items
        $items = [];
        $subtotal = 0.0;
        foreach ($orderPayload['product_items'] as $item) {
            $product = Product::where('whatsapp_retailer_id', $item['product_retailer_id'])
                ->orWhere('slug', $item['product_retailer_id'])
                ->first();

            $itemSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
            $subtotal += $itemSubtotal;

            $items[] = [
                'product_id' => $product?->id,
                'product_name' => $product?->name ?? $item['product_retailer_id'],
                'product_retailer_id' => $item['product_retailer_id'],
                'unit_price' => (float) $item['item_price'],
                'quantity' => (int) $item['quantity'],
                'subtotal' => $itemSubtotal,
            ];
        }

        // Create pending order
        $order = Order::create([
            'reference' => Order::generateReference(),
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'pending_payment',
            'subtotal' => $subtotal,
            'delivery_fee' => 0,
            'total' => $subtotal,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'whatsapp_order_id' => $orderPayload['order_id'] ?? null,
            'tracking_token' => Str::uuid(),
        ]);

        foreach ($items as $item) {
            OrderItem::create(array_merge(['order_id' => $order->id], $item));
        }

        // Save state
        $customer->update(['conversation_state' => [
            'step' => 'awaiting_suburb',
            'order_id' => $order->id,
        ]]);

        // Build order summary
        $summary = "Thanks {$customer->name}! Here's your order:\n\n";
        foreach ($items as $item) {
            $summary .= "• {$item['quantity']}x {$item['product_name']} — $" . number_format($item['unit_price'], 2) . "\n";
        }
        $summary .= "\n*Subtotal: $" . number_format($subtotal, 2) . "*";

        $this->messenger->sendText($phone, $summary);
        $this->messenger->sendText($phone, "📍 Where shall we deliver?\n\nReply with your suburb (e.g. Borrowdale, Glen Lorne, Avondale)");
    }

    // ── Handle incoming text replies from customer ───────────────────────────
    public function handleTextMessage(string $phone, string $text): void
    {
        $customer = $this->findOrCreateCustomer($phone);
        $state = $customer->conversation_state ?? [];
        $step = $state['step'] ?? 'idle';

        match ($step) {
            'awaiting_suburb' => $this->handleSuburbReply($customer, $state, $text),
            'awaiting_slot' => $this->handleSlotReply($customer, $state, $text),
            'awaiting_payment_method' => $this->handlePaymentMethodReply($customer, $state, $text),
            'awaiting_ecocash_number' => $this->handleEcoCashNumberReply($customer, $state, $text),
            default => $this->messenger->sendText($phone, "Hi! Browse our catalog to start shopping. 🛒"),
        };
    }

    private function handleSuburbReply(Customer $customer, array $state, string $suburb): void
    {
        $order = Order::find($state['order_id']);
        if (!$order) return;

        $zone = DeliveryZone::matchSuburb($suburb);

        if (!$zone) {
            $this->messenger->sendText($customer->whatsapp_phone,
                "Sorry, we don't deliver to *{$suburb}* yet. Please try another suburb or contact us.");
            return;
        }

        $deliveryFee = $this->deliveryService->calculateFeeAmount($zone, $order->subtotal);

        // Save address
        $address = DeliveryAddress::create([
            'customer_id' => $customer->id,
            'suburb' => $suburb,
            'city' => 'Harare',
        ]);

        $order->update([
            'delivery_address_id' => $address->id,
            'delivery_fee' => $deliveryFee,
            'total' => $order->subtotal + $deliveryFee,
        ]);

        // Get available slots
        $slots = $this->deliveryService->getAvailableSlots(today());
        if ($slots->isEmpty()) {
            $slots = $this->deliveryService->getAvailableSlots(today()->addDay());
        }

        if ($slots->isEmpty()) {
            $this->messenger->sendText($customer->whatsapp_phone, "Sorry, no delivery slots are available. Please try again later.");
            return;
        }

        $customer->update(['conversation_state' => array_merge($state, [
            'step' => 'awaiting_slot',
            'slot_options' => $slots->pluck('id')->toArray(),
        ])]);

        $feeText = $deliveryFee == 0 ? 'FREE' : '$' . number_format($deliveryFee, 2);
        $slotList = '';
        foreach ($slots->take(5) as $i => $slot) {
            $date = Carbon::parse($slot->date)->format('D d M');
            $slotList .= "\n[" . ($i + 1) . "] {$date} — {$slot->label}";
        }

        $this->messenger->sendText($customer->whatsapp_phone,
            "Delivery to *{$suburb}*: {$feeText}\n*Total: $" . number_format($order->total, 2) . "*\n\n📅 Choose a delivery slot:{$slotList}\n\nReply with the number (1, 2, 3...)");
    }

    private function handleSlotReply(Customer $customer, array $state, string $text): void
    {
        $choice = (int) trim($text) - 1;
        $slotOptions = $state['slot_options'] ?? [];

        if (!isset($slotOptions[$choice])) {
            $this->messenger->sendText($customer->whatsapp_phone, "Please reply with a valid number from the list.");
            return;
        }

        $slot = DeliverySlot::find($slotOptions[$choice]);
        $order = Order::find($state['order_id']);
        if (!$slot || !$order || !$slot->hasCapacity()) {
            $this->messenger->sendText($customer->whatsapp_phone, "That slot is no longer available. Please choose another.");
            return;
        }

        $order->update(['delivery_slot_id' => $slot->id]);

        $customer->update(['conversation_state' => array_merge($state, ['step' => 'awaiting_payment_method'])]);

        $this->messenger->sendInteractiveButtons(
            $customer->whatsapp_phone,
            "💳 How would you like to pay?\n\nTotal: $" . number_format($order->total, 2),
            ['EcoCash', 'OneMoney', 'Card (link)']
        );
    }

    private function handlePaymentMethodReply(Customer $customer, array $state, string $text): void
    {
        $text = strtolower(trim($text));
        $method = null;

        if (str_contains($text, 'ecocash') || $text === '1' || str_contains($text, 'btn_0')) {
            $method = 'ecocash';
        } elseif (str_contains($text, 'onemoney') || $text === '2' || str_contains($text, 'btn_1')) {
            $method = 'onemoney';
        } elseif (str_contains($text, 'card') || $text === '3' || str_contains($text, 'btn_2')) {
            $method = 'card';
        }

        if (!$method) {
            $this->messenger->sendText($customer->whatsapp_phone, "Please reply EcoCash, OneMoney, or Card.");
            return;
        }

        $order = Order::find($state['order_id']);
        $order->update(['payment_method' => $method]);

        if ($method === 'card') {
            $customer->update(['conversation_state' => ['step' => 'idle']]);
            $payLink = url("/pay/{$order->tracking_token}");
            $this->messenger->sendText($customer->whatsapp_phone,
                "🔗 Click to pay by card:\n{$payLink}\n\nWe'll confirm your order once payment is complete.");
        } else {
            $customer->update(['conversation_state' => array_merge($state, [
                'step' => 'awaiting_ecocash_number',
                'payment_method' => $method,
            ])]);
            $label = $method === 'ecocash' ? 'EcoCash' : 'OneMoney';
            $this->messenger->sendText($customer->whatsapp_phone, "📱 Enter your {$label} number:");
        }
    }

    private function handleEcoCashNumberReply(Customer $customer, array $state, string $text): void
    {
        $phone = preg_replace('/[^0-9+]/', '', $text);
        $order = Order::find($state['order_id']);
        $method = $state['payment_method'] ?? 'ecocash';

        $order->update(['ecocash_number' => $phone]);

        $customer->update(['conversation_state' => ['step' => 'idle']]);

        $this->messenger->sendText($customer->whatsapp_phone,
            "✅ Payment request sent!\n" .
            ($method === 'ecocash' ? "Dial *151*2*7# and enter your PIN.\n" : "Check your OneMoney app.\n") .
            "Waiting for confirmation... ⏳");

        // Dispatch payment polling job
        // TODO: dispatch(new \App\Jobs\PollPaynowPaymentJob($order));
        // For now log it
        Log::info("Payment initiated for order {$order->reference}", ['method' => $method, 'phone' => $phone]);
    }

    private function findOrCreateCustomer(string $phone): Customer
    {
        return Customer::firstOrCreate(
            ['whatsapp_phone' => $phone],
            ['name' => 'Customer ' . substr($phone, -4), 'phone' => $phone]
        );
    }
}
