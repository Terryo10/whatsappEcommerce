# 🥦 VegShop Platform — Full Architecture Plan

## Overview

A single Laravel backend that powers:
- WhatsApp catalog commerce (native WhatsApp cart & checkout)
- React web storefront
- Flutter mobile app
- Driver delivery app (Flutter)
- Filament admin panel (stock, orders, drivers, catalog sync)

---

## 1. System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL BACKEND (API)                     │
│                                                             │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│  │  Filament   │  │  REST API    │  │  WhatsApp        │   │
│  │  Admin      │  │  (Sanctum)   │  │  Webhook Handler │   │
│  └─────────────┘  └──────────────┘  └──────────────────┘   │
│         │                │                    │             │
│  ┌──────▼──────────────────────────────────────▼──────────┐ │
│  │              Core Services Layer                        │ │
│  │  Products │ Orders │ Delivery │ Drivers │ Paynow        │ │
│  └─────────────────────────────────────────────────────────┘ │
│         │                                                    │
│  ┌──────▼──────────────────────────────────────────────┐    │
│  │           Meta Graph API Sync Service               │    │
│  │   (Catalog products ↔ WhatsApp Commerce Manager)    │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
         │              │              │              │
    ┌────▼────┐   ┌─────▼────┐  ┌─────▼──────┐ ┌────▼──────┐
    │WhatsApp │   │  React   │  │  Flutter   │ │  Flutter  │
    │Catalog  │   │  Web App │  │  User App  │ │Driver App │
    └─────────┘   └──────────┘  └────────────┘ └───────────┘
```

---

## 2. Database Schema

### Products & Catalog
```sql
products
  id, name, description, price_usd, price_zwl
  image_url, category_id, unit (kg/bunch/piece)
  stock_qty, low_stock_threshold
  whatsapp_retailer_id  -- Meta catalog product ID
  is_active, is_whatsapp_visible
  created_at, updated_at

categories
  id, name, whatsapp_catalog_set_id, sort_order

product_images
  id, product_id, url, is_primary, sort_order
```

### Orders
```sql
orders
  id, reference (ORD-2025-XXXX)
  customer_id, channel (whatsapp|web|app)
  status (pending_payment|paid|preparing|assigned|in_transit|delivered|cancelled)
  subtotal, delivery_fee, total
  currency (USD|ZWL)
  payment_method (ecocash|onemoney|card)
  payment_status (pending|paid|failed)
  paynow_poll_url
  delivery_address_id
  delivery_slot_id
  driver_id (nullable)
  tracking_token (unique UUID for public tracking link)
  notes
  whatsapp_order_id  -- from WhatsApp webhook
  created_at, updated_at

order_items
  id, order_id, product_id
  product_name, unit_price, quantity, subtotal

delivery_slots
  id, date, label (e.g. "Morning 7am-12pm")
  time_from, time_to
  max_orders, current_orders
  is_available

delivery_addresses
  id, customer_id, label
  suburb, city, lat, lng
  instructions
```

### Delivery
```sql
drivers
  id, user_id, name, phone
  vehicle_type, vehicle_plate
  is_active, is_available
  current_lat, current_lng
  last_location_update

deliveries
  id, order_id, driver_id
  status (assigned|picked_up|in_transit|delivered|failed)
  assigned_at, picked_up_at, delivered_at
  driver_notes, delivery_photo_url
  estimated_arrival_at

delivery_zones
  id, name, suburb_keywords[]
  base_fee, per_km_fee
  free_delivery_above (order value)
  estimated_minutes
```

### Customers
```sql
customers
  id, name, phone, email
  whatsapp_phone (E.164 format e.g. +263771234567)
  preferred_payment_method
  created_at

customer_addresses
  -- same as delivery_addresses linked to customer
```

---

## 3. Laravel Modules / Services

### A. WhatsApp Catalog Sync Service
**Triggered:** When product is created/updated/deleted via Filament

```php
// app/Services/WhatsApp/CatalogSyncService.php

class CatalogSyncService
{
    // Push single product to Meta Commerce Manager
    public function syncProduct(Product $product): void

    // Delete product from Meta catalog
    public function deleteProduct(Product $product): void

    // Bulk sync all active products (scheduled daily)
    public function bulkSync(): void

    // Update stock visibility (hide if out of stock)
    public function updateAvailability(Product $product): void
}
```

**How it works:**
- Listens to `ProductObserver` (saved/deleted events)
- Calls `POST /catalog_id/products` on Meta Graph API
- Stores `whatsapp_retailer_id` back on product
- Logs sync status so Filament admin can see failures

---

### B. WhatsApp Webhook Handler
**Handles:** Incoming messages + ORDER WEBHOOK from WhatsApp

```php
// app/Http/Controllers/WhatsApp/WebhookController.php

// WhatsApp fires this when a customer completes
// cart + taps "Checkout" inside WhatsApp
public function handleOrderMessage(array $payload): void
{
    // Payload contains:
    // - customer phone
    // - list of products + quantities (from their cart)
    // - customer's message text

    // 1. Find or create customer
    // 2. Create Order record (status: pending_payment)
    // 3. Send payment selection message back to customer
    //    "How would you like to pay?
    //     [EcoCash] [OneMoney] [Card Link]"
}

public function handlePaymentMethodReply(string $phone, string $method): void
{
    // Customer replied with their payment choice
    // 1. Get their pending order
    // 2. Ask for EcoCash number if mobile money
    // 3. Or send card payment link
}
```

**WhatsApp Order Webhook payload example:**
```json
{
  "type": "order",
  "order": {
    "catalog_id": "your_catalog_id",
    "product_items": [
      { "product_retailer_id": "tomatoes-1kg", "quantity": 2, "item_price": 1.50, "currency": "USD" },
      { "product_retailer_id": "onions-500g",  "quantity": 1, "item_price": 0.80, "currency": "USD" }
    ],
    "text": "Please deliver to Borrowdale"
  }
}
```

---

### C. Payment Service (Paynow)
```php
// app/Services/Payment/PaynowService.php

// Initiate EcoCash push payment
public function initiateEcoCash(Order $order, string $phone): PaymentInitResult

// Initiate OneMoney push payment  
public function initiateOneMoney(Order $order, string $phone): PaymentInitResult

// Get web redirect URL for card payment
public function getCardRedirectUrl(Order $order): string

// Check payment status (called by polling job)
public function pollStatus(string $pollUrl): PaymentStatus

// Handle Paynow result webhook
public function handleResultWebhook(array $payload): void
```

**Polling Job:**
```php
// Runs every 5 seconds, up to 3 minutes
// On success: marks order paid, triggers delivery slot assignment
// On timeout: marks order failed, notifies customer
class PollPaynowPaymentJob implements ShouldQueue { }
```

---

### D. Delivery Service
```php
// app/Services/Delivery/DeliveryService.php

// Calculate delivery fee based on zone + order value
public function calculateFee(string $suburb, float $orderTotal): DeliveryFeeResult

// Get available delivery slots for a date
public function getAvailableSlots(Carbon $date): Collection

// Assign driver to order (manual or auto)
public function assignDriver(Order $order, Driver $driver): Delivery

// Generate public tracking token
public function generateTrackingLink(Order $order): string

// Update driver location (called by driver app every 30s)
public function updateDriverLocation(Driver $driver, float $lat, float $lng): void

// Get order tracking info (public, no auth)
public function getTrackingInfo(string $token): TrackingInfo
```

---

## 4. API Routes

### Public / Customer APIs (Sanctum Auth)
```
POST   /api/auth/login
POST   /api/auth/register

GET    /api/products
GET    /api/products/{id}
GET    /api/categories

POST   /api/orders                    -- create order (web/app)
GET    /api/orders/{id}
GET    /api/orders/history

GET    /api/delivery/slots?date=      -- available slots
POST   /api/delivery/calculate-fee    -- suburb → fee

POST   /api/payment/ecocash           -- initiate EcoCash
POST   /api/payment/card              -- get card redirect URL
GET    /api/payment/{orderId}/status

GET    /api/track/{token}             -- PUBLIC, no auth needed
```

### Driver APIs (Sanctum Auth, driver role)
```
POST   /api/driver/auth/login
GET    /api/driver/deliveries         -- assigned deliveries
GET    /api/driver/deliveries/{id}
PATCH  /api/driver/deliveries/{id}/status   -- update status
POST   /api/driver/location           -- push GPS coordinates
POST   /api/driver/deliveries/{id}/photo    -- proof of delivery
```

### WhatsApp Webhooks (no auth, Meta signature verification)
```
GET    /webhook/whatsapp              -- Meta verification
POST   /webhook/whatsapp              -- incoming messages + orders
POST   /webhook/paynow/result         -- payment result callback
```

---

## 5. Filament Admin Panels

### Dashboard
- Total orders today / this week
- Revenue (USD + ZWL)
- Orders by channel (WhatsApp / Web / App)
- Low stock alerts
- Active drivers on map

### Products Resource
- CRUD with image upload
- **Sync to WhatsApp** button per product
- Stock management (qty, low stock threshold)
- Bulk sync all products
- WhatsApp catalog sync status indicator (✅ synced / ❌ failed / 🔄 pending)
- Toggle WhatsApp visibility

### Orders Resource
- All orders with filters (channel, status, date, driver)
- Order detail view with items
- Assign driver manually
- Change delivery slot
- Payment status tracking
- WhatsApp message thread link

### Delivery Slots Resource
- Create slots per date (Morning / Afternoon / Evening)
- Set max orders per slot
- See current bookings
- Block out dates (public holidays)

### Delivery Zones Resource
- Zone name + suburb keywords
- Base fee (USD), per-km rate
- Free delivery threshold
- Estimated delivery time

### Drivers Resource
- Driver profiles
- Active deliveries
- Live location map (Filament map widget)
- Delivery history + performance stats
- Toggle availability

### Catalog Sync Logs
- Table of every sync attempt
- Status (success/fail), timestamp, product name
- Error message if failed
- Manual retry button

---

## 6. Delivery Flow (End-to-End)

```
1. Customer places order (WhatsApp / Web / App)
2. Customer selects delivery slot + address
3. Payment processed via Paynow
4. ✅ Payment confirmed
   → Order status: "paid" → "preparing"
   → Filament admin notified
   → Customer gets WhatsApp: "Order confirmed! 🎉"

5. Admin prepares order in Filament
   → Assigns driver (or auto-assign nearest available)
   → Order status: "assigned"
   → Driver gets push notification in driver app

6. Driver picks up order
   → Taps "Picked Up" in driver app
   → Order status: "in_transit"
   → Customer gets WhatsApp + tracking link:
     "Your order is on the way! 🚗
      Track your driver: https://vegshop.co.zw/track/abc123"

7. Tracking page (React/Flutter) shows:
   → Driver name + vehicle
   → Live GPS dot on map (updates every 30s)
   → Estimated arrival time
   → Order items summary

8. Driver delivers
   → Takes photo, taps "Delivered"
   → Order status: "delivered"
   → Customer gets WhatsApp: "Delivered! ✅ Thank you!"
   → Tracking link shows "Delivered" state
```

---

## 7. Delivery Pricing System

```php
// Three-tier pricing model

// Tier 1: Zone-based flat fee
// e.g. Borrowdale = $2.00, Chitungwiza = $4.50

// Tier 2: Free delivery above threshold
// e.g. Orders over $30 = free within city zones

// Tier 3: Per-km surcharge for far suburbs
// base_fee + (distance_km * per_km_rate)

// Delivery fee calculation:
public function calculateFee(string $suburb, float $orderTotal): DeliveryFeeResult
{
    $zone = DeliveryZone::matchSuburb($suburb);

    if (!$zone) {
        return DeliveryFeeResult::unavailable();
    }

    if ($orderTotal >= $zone->free_delivery_above) {
        return DeliveryFeeResult::free();
    }

    $fee = $zone->base_fee;

    // Optional: add distance component
    if ($zone->per_km_fee > 0 && $lat && $lng) {
        $distanceKm = $this->calculateDistance($lat, $lng, $zone->hub_lat, $zone->hub_lng);
        $fee += $distanceKm * $zone->per_km_fee;
    }

    return new DeliveryFeeResult(
        fee: round($fee, 2),
        currency: 'USD',
        estimatedMinutes: $zone->estimated_minutes,
        zoneName: $zone->name,
    );
}
```

**Delivery Slot Structure:**
```
Monday–Friday:
  Morning:   07:00 – 12:00  (max 20 orders)
  Afternoon: 12:00 – 17:00  (max 20 orders)
  Evening:   17:00 – 20:00  (max 10 orders)

Saturday:
  Morning:   07:00 – 13:00  (max 30 orders)
  Afternoon: 13:00 – 17:00  (max 15 orders)

Sunday: Closed (or special slots only)
```

---

## 8. WhatsApp Conversation Flow (Checkout)

```
[Customer browses native WhatsApp catalog]
[Taps products → Add to cart → Checkout]
      ↓
WhatsApp sends ORDER webhook to your backend
      ↓
Bot: "Thanks Tendai! 🛒
      Your order:
      • 2x Tomatoes 1kg — $3.00
      • 1x Onions 500g  — $0.80
      Subtotal: $3.80

      📍 Where shall we deliver?
      Reply with your suburb (e.g. Borrowdale, Glen Lorne)"
      ↓
Customer: "Borrowdale"
      ↓
Bot: "Delivery to Borrowdale: $2.00
      Total: $5.80

      📅 Choose a delivery slot:
      [1] Tomorrow Morning (7am–12pm)
      [2] Tomorrow Afternoon (12pm–5pm)
      [3] Day after Morning"
      ↓
Customer: "1"
      ↓
Bot: "💳 How would you like to pay?
      [1] EcoCash
      [2] OneMoney
      [3] Card (link)"
      ↓
Customer: "1"
      ↓
Bot: "📱 Enter your EcoCash number:"
      ↓
Customer: "0771234567"
      ↓
[Backend initiates Paynow EcoCash push]
      ↓
Bot: "✅ Payment request sent!
      Dial *151*2*7# and enter your PIN.
      Waiting for confirmation... ⏳"
      ↓
[Paynow confirms payment]
      ↓
Bot: "🎉 Payment confirmed! Order #ORD-2025-0042
      We'll notify you when your driver is assigned.
      
      Expected: Tomorrow 7am–12pm"
```

---

## 9. Tech Stack Summary

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.3 |
| Admin Panel | Filament v3 |
| Database | MySQL 8 |
| Cache/Queue | Redis + Laravel Horizon |
| WhatsApp | Meta Cloud API (direct, no BSP needed) |
| Catalog | Meta Commerce Manager + Graph API |
| Payments | Paynow Zimbabwe PHP SDK |
| Web Frontend | React + Vite + TailwindCSS |
| Mobile App | Flutter |
| Driver App | Flutter (separate app or flavor) |
| Maps/Tracking | Google Maps API (or OpenStreetMap/Leaflet) |
| Push Notifications | Firebase FCM (Flutter apps) |
| File Storage | S3 or local (product images) |
| Hosting | VPS (Ubuntu) + Nginx + SSL |

---

## 10. Build Order (Recommended Phases)

### Phase 1 — Core Backend (Week 1–2)
- [ ] Laravel project setup, Filament install
- [ ] Database migrations (products, orders, customers, delivery)
- [ ] Filament: Products + Categories CRUD
- [ ] Paynow service (EcoCash + card)
- [ ] Basic REST API with Sanctum auth

### Phase 2 — WhatsApp Integration (Week 2–3)
- [ ] Meta Business + Commerce Manager setup
- [ ] Catalog sync service (create/update/delete products)
- [ ] WhatsApp webhook handler
- [ ] Order creation from WhatsApp order messages
- [ ] WhatsApp conversation bot (delivery slot, payment)
- [ ] Paynow polling job

### Phase 3 — Delivery System (Week 3–4)
- [ ] Delivery zones + fee calculator
- [ ] Delivery slots management in Filament
- [ ] Driver management in Filament
- [ ] Driver assignment (manual first)
- [ ] Tracking token generation
- [ ] Public tracking page (React)
- [ ] Driver location update API

### Phase 4 — Customer Apps (Week 4–6)
- [ ] React web app (product browsing, cart, checkout, tracking)
- [ ] Flutter user app
- [ ] Flutter driver app (deliveries list, status updates, GPS push)
- [ ] Firebase push notifications

### Phase 5 — Polish (Week 6–7)
- [ ] Filament dashboard widgets (revenue, orders, map)
- [ ] Auto-assign nearest driver
- [ ] Low stock WhatsApp alerts to admin
- [ ] Order analytics + export
- [ ] ZIMRA fiscalisation (if required)
```
