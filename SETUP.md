# VegShop Platform — Setup & Reference

## Tech Stack
- **Backend**: Laravel 13, PHP 8.3, SQLite (dev) / MySQL 8 (prod)
- **Admin Panel**: Filament v5.6
- **WhatsApp**: Meta Cloud API (direct, no BSP)
- **Payments**: Paynow Zimbabwe (EcoCash, OneMoney, Card)

---

## Quick Start (Local Dev)

```bash
# Install dependencies
composer install

# Copy env
cp .env.example .env   # or edit .env directly

# Generate key (already set)
php artisan key:generate

# Run migrations + seed sample data
php artisan migrate --seed

# Create storage symlink (product images)
php artisan storage:link

# Start server
php artisan serve
```

Visit: http://localhost:8000/admin

---

## Admin Panel Login

| Field    | Value                  |
|----------|------------------------|
| URL      | http://localhost:8000/admin |
| Email    | admin@vegshop.co.zw    |
| Password | admin123               |

> Change this password immediately in production via Filament profile page.

---

## Environment Variables to Fill In

Open `.env` and fill these before going live:

### Meta / WhatsApp
```env
META_GRAPH_API_VERSION=v21.0
META_ACCESS_TOKEN=        # Meta Business > Settings > System Users > Generate token
META_CATALOG_ID=          # Commerce Manager > Catalog > Settings > Catalog ID
META_PHONE_NUMBER_ID=     # Meta Business > WhatsApp > API Setup > Phone Number ID
META_WHATSAPP_VERIFY_TOKEN=vegshop_verify_token   # Can change this to anything secret
META_APP_SECRET=          # Meta App Dashboard > App Secret
```

### Paynow Zimbabwe
```env
PAYNOW_INTEGRATION_ID=    # From https://paynow.co.zw merchant portal
PAYNOW_INTEGRATION_KEY=   # From Paynow merchant portal
PAYNOW_RESULT_URL="${APP_URL}/webhook/paynow/result"
PAYNOW_RETURN_URL="${APP_URL}/payment/return"
```

### App URL (production)
```env
APP_URL=https://vegshop.co.zw
APP_ENV=production
APP_DEBUG=false
```

### Database (production — switch from SQLite to MySQL)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vegshop
DB_USERNAME=vegshop_user
DB_PASSWORD=your_password
```

---

## Meta Webhook Setup

1. Go to **Meta for Developers** → Your App → WhatsApp → Configuration
2. Set Webhook URL: `https://yourdomain.com/webhook/whatsapp`
3. Set Verify Token: `vegshop_verify_token` (or whatever you set in `META_WHATSAPP_VERIFY_TOKEN`)
4. Subscribe to these webhook fields:
   - `messages`
   - `message_deliveries`
   - `message_reads`
5. Click **Verify and Save**

> The verification endpoint is `GET /webhook/whatsapp` — it echoes back the `hub.challenge` if the token matches.

---

## WhatsApp Catalog Sync

### How it works
- When you **save a product** in Filament, `ProductObserver` auto-triggers a sync to Meta Commerce Manager
- You can also **manually sync** per product (Sync to WA button on product edit page)
- **Bulk sync** all active products via Products list → Sync All to WhatsApp button
- Sync status shows on each product row: Synced / Failed / Pending / Not Synced
- Failed syncs appear in **Catalog > Sync Logs** with a Retry button

### WhatsApp Retailer ID
Each product gets a `whatsapp_retailer_id` stored after first sync. This is the `retailer_id` used in the Meta catalog. Before syncing, it defaults to the product slug.

---

## WhatsApp Conversation Flow (Customer Checkout)

```
Customer browses catalog in WhatsApp → taps Checkout
  → Bot: Shows order summary, asks for suburb
Customer replies: "Borrowdale"
  → Bot: Shows delivery fee + total, lists available slots
Customer replies: "1" (slot choice)
  → Bot: Shows payment options (EcoCash / OneMoney / Card)
Customer replies: "EcoCash" or "1"
  → Bot: Asks for EcoCash number
Customer replies: "0771234567"
  → Bot: Sends payment request, waits for Paynow confirmation
Paynow confirms payment
  → Bot: Sends order confirmation with reference number
```

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/WhatsApp/CatalogSyncService.php` | Sync products to Meta Commerce Manager |
| `app/Services/WhatsApp/WhatsAppMessagingService.php` | Send WhatsApp messages (text, buttons, lists) |
| `app/Services/WhatsApp/ConversationBotService.php` | WhatsApp checkout conversation state machine |
| `app/Services/Delivery/DeliveryService.php` | Fee calc, slot availability, driver assignment, tracking |
| `app/Http/Controllers/WhatsApp/WebhookController.php` | Receive & verify Meta webhooks |
| `app/Observers/ProductObserver.php` | Auto-sync product changes to Meta |
| `app/Providers/Filament/AdminPanelProvider.php` | Filament panel config + widgets |
| `config/services.php` | Meta & Paynow service credentials config |

---

## API Routes Summary

### Public
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/webhook/whatsapp` | Meta webhook verification |
| POST | `/webhook/whatsapp` | Incoming WhatsApp messages/orders |
| GET | `/track/{token}` | Public order tracking (no auth) |

### Customer Auth
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/auth/register` | None | Register new customer |
| POST | `/api/auth/login` | None | Login, returns Sanctum token |
| POST | `/api/auth/logout` | Bearer token | Revoke current token |
| GET | `/api/auth/me` | Bearer token | Current user + customer profile |

### Public (no auth needed)
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/products` | List products |
| GET | `/api/products/{id}` | Single product |
| GET | `/api/categories` | List categories |
| GET | `/api/delivery/slots` | Available delivery slots |
| POST | `/api/delivery/calculate-fee` | Calculate delivery fee for suburb |
| GET | `/api/track/{token}` | Public order tracking |

### Customer Protected (Bearer token required)
| Method | Route | Description |
|--------|-------|-------------|
| POST | `/api/orders` | Create order |
| GET | `/api/orders` | Order history |
| GET | `/api/orders/{id}` | Order detail |
| POST | `/api/payment/ecocash` | Initiate EcoCash payment |
| POST | `/api/payment/card` | Get card payment link |
| GET | `/api/payment/{order}/status` | Payment status |

### Driver Auth
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/driver/auth/login` | None | Driver login, returns Sanctum token |
| POST | `/api/driver/auth/logout` | Bearer token | Revoke current token |
| GET | `/api/driver/auth/me` | Bearer token | Current user + driver profile |

### Driver Protected (Bearer token required)
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/driver/deliveries` | My assigned deliveries |
| GET | `/api/driver/deliveries/{id}` | Delivery detail with order |
| PATCH | `/api/driver/deliveries/{id}/status` | Update status (picked_up/in_transit/delivered) |
| POST | `/api/driver/location` | Push GPS coordinates (lat, lng) |
| POST | `/api/driver/deliveries/{id}/photo` | Upload proof of delivery photo |

### Using the API (example)
```bash
# 1. Login
TOKEN=$(curl -s -X POST /api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' | jq -r '.token')

# 2. Use token
curl /api/orders -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

---

## Filament Admin Resources

| Resource | Path | Description |
|----------|------|-------------|
| Products | `/admin/products` | CRUD + WhatsApp sync |
| Categories | `/admin/categories` | Category management |
| Sync Logs | `/admin/catalog-sync-logs` | WhatsApp sync history + retry |
| Orders | `/admin/orders` | All orders, assign drivers |
| Drivers | `/admin/drivers` | Driver profiles + GPS |
| Delivery Slots | `/admin/delivery-slots` | Slot management per date |
| Delivery Zones | `/admin/delivery-zones` | Zone pricing config |

---

## Sample Delivery Zones (Seeded)

| Zone | Suburbs | Base Fee | Free Delivery Above |
|------|---------|----------|---------------------|
| Harare North | Borrowdale, Glen Lorne, Chisipite, Highlands... | $2.00 | $30 |
| Harare Central | Avondale, Milton Park, Belgravia, Newlands... | $1.50 | $25 |
| Harare South | Budiriro, Mufakose, Glenview, Highfield... | $3.00 | $40 |
| Chitungwiza | Chitungwiza, St Marys, Zengeza... | $4.50 | $50 |

---

## What's Left (Phase 3+)

- [ ] Paynow SDK integration (EcoCash push payment polling job)
- [ ] Firebase FCM push notifications for driver app
- [ ] React web storefront (product browsing, cart, checkout, tracking page)
- [ ] Flutter customer app
- [ ] Flutter driver app
- [ ] Auto-assign nearest available driver
- [ ] WhatsApp low-stock alerts to admin
- [ ] ZIMRA fiscalisation

---

## Database (SQLite for dev)

File: `database/database.sqlite`

To reset and reseed:
```bash
php artisan migrate:fresh --seed
```
