# Partial Delivery — Integration Guide

## TL;DR (read this if nothing else)

**What it is:** a Shopware 6 plugin that records *partial deliveries* of an order's line
items (which items, how many, package/tracking) and can optionally trigger a *partial
payment capture* for those deliveries.

**What it gives integrators:** a stable, vendor-neutral way for an **external system**
(ERP / middleware) or a **payment provider** to create, update, list, and capture partial
deliveries — without touching the plugin's internals.

**You can do three things:**
1. **Create / update** partial deliveries (which line items shipped, quantities, tracking).
2. **Read** delivered-vs-remaining per line item.
3. **Capture** payment for delivered items — provider-agnostic, idempotent (never double-charges).

**Two ways to integrate (same logic underneath):**
- **Over HTTP** — Shopware Admin API endpoints under `/api/_action/partial-delivery/*`.
- **In-process** — inject the public `PartialDeliveryService`, listen to events, or register
  a payment-capture handler. (For plugins living in the same Shopware install.)

**Fastest path:** create an Admin API integration → get a token → `POST /api/_action/partial-delivery/shipments`.
Full walkthrough in **[Quick start](#quick-start-step-by-step)**.

> Payment capture is **off by default** (returns `unsupported`, charges nothing) until a
> payment provider handler is registered — see [§5](#5-adding-a-payment-provider).
> The old admin-UI endpoints (`/api/_action/partial-shipment-delivery…`) are **unchanged**
> and still power the "Shipments" tab.

---

## Contents

- [Quick start (step by step)](#quick-start-step-by-step)
- [1. Requirements & dependencies](#1-requirements--dependencies)
- [2. Authentication](#2-authentication)
- [3. HTTP API reference](#3-http-api-reference)
- [4. Using the plugin from another Shopware plugin (PHP)](#4-using-the-plugin-from-another-shopware-plugin-php)
- [5. Adding a payment provider](#5-adding-a-payment-provider)
- [6. Events](#6-events)
- [7. Data model](#7-data-model)
- [8. Error handling](#8-error-handling)
- [9. Notes by integrator role](#9-notes-by-integrator-role)
- [10. Configuration](#10-configuration)

---

## Quick start (step by step)

A complete walkthrough from a fresh install to a captured partial delivery. Replace
`https://your-shop.example` and the IDs with your own.

**1. Install & activate the plugin.** Shopware **Commercial (Return Management)** must be
installed and active first, otherwise activation is refused (see [§1](#1-requirements--dependencies)).
The schema migration runs automatically on install/update; to run it manually:

```bash
bin/console database:migrate --all PartialDelivery
bin/console cache:clear
```

**2. Create an Admin API integration.** In Admin → `Settings → System → Integrations → Add
integration`. Either tick **Administrator** (simplest — bypasses ACL), or assign it an ACL
role that includes the `partial_delivery: read / create / update` privileges. Copy the
**Access key ID** and **Secret access key**.

**3. Get a bearer token:**

```bash
curl -s -X POST "https://your-shop.example/api/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{"grant_type":"client_credentials","client_id":"<accessKeyId>","client_secret":"<secretAccessKey>"}'
# → copy the "access_token" from the response
```

**4. Create a partial delivery** (identify the line by its UUID, or by order number + SKU):

```bash
curl -s -X POST "https://your-shop.example/api/_action/partial-delivery/shipments" \
  -H "Authorization: Bearer <access_token>" -H "Content-Type: application/json" \
  -d '{
    "source": "external-system",
    "externalReference": "ERP-DELIVERY-123",
    "shipments": [
      { "orderLineItemId": "<orderLineItemId>", "quantity": 2, "package": "Box 1", "trackingCode": "DHL-00123" }
    ]
  }'
# → 201 { "success": true, "created": [ { "partialDeliveryId": "…" } ], "skipped": [] }
```

**5. Check what is delivered vs remaining:**

```bash
curl -s "https://your-shop.example/api/_action/partial-delivery/order/<orderId>" \
  -H "Authorization: Bearer <access_token>"
# → quantityOrdered / quantityDelivered / quantityRemaining + the shipment rows per line item
```

**6. (Optional) Update a delivery** (only send what changes):

```bash
curl -s -X PATCH "https://your-shop.example/api/_action/partial-delivery/shipments/<partialDeliveryId>" \
  -H "Authorization: Bearer <access_token>" -H "Content-Type: application/json" \
  -d '{ "trackingCode": "DHL-NEW-999" }'
```

**7. (Optional) Capture payment** for the delivered items:

```bash
curl -s -X POST "https://your-shop.example/api/_action/partial-delivery/capture" \
  -H "Authorization: Bearer <access_token>" -H "Content-Type: application/json" \
  -d '{ "orderId": "<orderId>", "amount": 99.90, "currency": "EUR" }'
# → { "status": "unsupported", … } until a provider handler is registered (see §5),
#   then "captured" / "requested". Safe to call repeatedly — already-captured rows are skipped.
```

That's the whole loop. Everything below is the detailed reference.

> A ready-to-import **Postman collection** (`partial-delivery.postman_collection.json`) ships
> with the plugin and contains all of the above requests with variables and a token helper.

---

## 1. Requirements & dependencies

- Shopware 6.6 or 6.7.
- **Shopware Commercial (Return Management)** must be installed and active — the plugin
  decorates a Commercial service for its return flow and will refuse to activate without
  it. (Declared in `composer.json` `suggest`; enforced in `PartialDelivery::activate()`.)

---

## 2. Authentication

All endpoints live under the Shopware **Admin API** (`_routeScope: api`). Authenticate with
a standard Admin API **integration** (recommended) or an admin user token.

### Create an integration (recommended for ERP / middleware)

`Admin → Settings → System → Integrations → Add integration` → copy the **Access key ID**
and **Secret access key**.

### Obtain a bearer token (client credentials)

```bash
curl -X POST "https://your-shop.example/api/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "<accessKeyId>",
    "client_secret": "<secretAccessKey>"
  }'
```

Use the returned `access_token` as `Authorization: Bearer <token>` on every request.


---

## 3. HTTP API reference

Base path: `/api/_action/partial-delivery`

| Method | Path | ACL privilege | Purpose |
|---|---|---|---|
| `POST`  | `/api/_action/partial-delivery/shipments` | `partial_delivery:create` | Create one or more partial deliveries |
| `PATCH` | `/api/_action/partial-delivery/shipments/{id}` | `partial_delivery:update` | Update a single partial delivery (DAL) |
| `GET`   | `/api/_action/partial-delivery/order/{orderId}` | `partial_delivery:read` | List partial deliveries of an order, grouped per line item |
| `POST`  | `/api/_action/partial-delivery/capture` | `partial_delivery:update` | Trigger a payment-aware capture (provider-agnostic) |

> **ACL:** write/read routes enforce `_acl` using the entity privileges Shopware auto-generates
> for the `partial_delivery` entity. Admin users bypass ACL; an **integration** must be assigned a
> role granting the relevant `partial_delivery:*` privileges (`Settings → System → Integrations`).

A generic DAL admin-API CRUD for the entity is also auto-available at
`/api/partial-delivery` and `/api/search/partial-delivery` (standard Shopware repository
routes) if you need raw read/write access.

### 3.1 Create partial deliveries

`POST /api/_action/partial-delivery/shipments`

Top-level `source` and `externalReference` are optional defaults applied to every shipment
that does not specify its own.

A line item can be identified in **three** ways (checked in this order):

1. `orderLineItemId` — the Shopware order line item UUID (most precise).
2. `orderId` + `productNumber` — resolve the line item by SKU within an order.
3. `orderNumber` + `productNumber` — same, but by human order number.

```jsonc
{
  "source": "external-system",              // optional: who is sending this (free text)
  "externalReference": "ERP-DELIVERY-123",  // optional: your reference for the whole delivery
  "shipments": [
    {
      "orderLineItemId": "0195a178f96b7345ad27051c34609e52",
      "quantity": 2,                         // required, must be > 0 and <= remaining
      "package": "Box 1",                    // optional
      "trackingCode": "DHL-00123",           // optional
      "amount": 49.95,                       // optional, e.g. captured/invoiced amount
      "source": "manual",                    // optional override
      "externalReference": "ERP-LINE-9"      // optional override
    },
    {
      "orderNumber": "10029",
      "productNumber": "SKU-001",
      "quantity": 1
    }
  ]
}
```

**Response** (`201 Created` if at least one row was created, `422` if all were skipped):

```json
{
  "success": true,
  "created": [
    { "orderLineItemId": "0195a178f96b7345ad27051c34609e52", "partialDeliveryId": "01933f..." }
  ],
  "skipped": []
}
```

Each invalid item is reported in `skipped` (the rest still succeed):

```json
{
  "success": false,
  "created": [],
  "skipped": [
    { "index": 0, "orderLineItemId": "0195...", "reason": "Cannot deliver 99. Only 1 remaining." }
  ]
}
```

**Validation rules**

- `quantity` must be a positive integer.
- The line item must resolve and be a real product (`type = product`, has a `productId`).
- `quantity` must not exceed `orderedQuantity − alreadyDelivered`.

### 3.2 Update a partial delivery

`PATCH /api/_action/partial-delivery/shipments/{id}`

The Shopware-6-way update: writes through the DAL repository. Send **only** the fields you
want to change — omitted fields are left untouched.

```json
{
  "quantity": 2,
  "package": "Box B",
  "trackingCode": "TestTracking-99999",
  "amount": 39.90
}
```

Updatable fields: `quantity`, `package`, `trackingCode`, `amount`, `source`, `externalReference`.
When `quantity` is supplied it is validated against the line item's remaining quantity
(**excluding this row**, so you can lower or keep the same quantity safely).

**Editing an already-captured delivery** is governed by the `captureUpdatePolicy` plugin
setting (see [§10](#10-configuration)):

- `protect` (default): changing a **financial** field (`quantity`/`amount`) of a captured
  delivery is rejected with `422`. Logistics fields (`package`/`trackingCode`) stay editable.
- `flag`: the change is applied, the row is marked `payment_status = adjustment_required`,
  and a `PartialDeliveryCaptureAdjustmentRequiredEvent` is dispatched. No money is moved
  automatically — a listener decides whether to refund or capture the difference.

**Responses**

| Outcome | HTTP | Body |
|---|---|---|
| Updated | 200 | `{ "success": true, "partialDeliveryId": "...", "reason": null }` |
| Invalid quantity | 422 | `{ "success": false, "partialDeliveryId": "...", "reason": "Cannot deliver 99. Only 3 remaining." }` |
| Not found | 404 | `{ "success": false, "partialDeliveryId": "...", "reason": "Partial delivery not found." }` |

> The legacy `PATCH /api/_action/partial-shipment-delivery/update/{id}` endpoint still exists
> for the admin UI and now delegates to the same DAL-based service internally.

### 3.3 List partial deliveries of an order

`GET /api/_action/partial-delivery/order/{orderId}`

```json
{
  "orderId": "019edac7b4347394b2fb2f9762876e61",
  "lineItems": [
    {
      "orderLineItemId": "019edac7b43273d4b3e657138cd82119",
      "productNumber": "SWDEMO10005.1",
      "quantityOrdered": 3,
      "quantityDelivered": 1,
      "quantityRemaining": 2,
      "shipments": [
        {
          "id": "01933f...",
          "quantity": 1,
          "package": "Box 1",
          "trackingCode": "DHL-00123",
          "paymentStatus": "captured",
          "paymentReference": "PSP-REF-abc",
          "source": "external-system",
          "externalReference": "ERP-DELIVERY-123",
          "amount": 49.95,
          "createdAt": "2026-06-24T10:15:00+00:00"
        }
      ]
    }
  ]
}
```

### 3.4 Trigger a payment capture

`POST /api/_action/partial-delivery/capture`

Capture is **decoupled from creation**: storing a delivery never moves money. Call this
endpoint when you want the order's payment to be (partially) captured. The plugin resolves
a registered payment-capture handler for the order's payment method and delegates to it.

**Capture is idempotent and incremental.** Only deliveries that are not yet
captured/pending are processed, and only those rows receive the result. So in a flow
where you ship 2 of 3 line items and capture them, then later ship and capture the 3rd,
the second capture call **only charges the 3rd** — already-captured rows are never
re-charged. Re-calling capture when everything matching is already captured is a safe
no-op (`status: captured`, message "Nothing to capture…").

```json
{
  "orderId": "019edac7b4347394b2fb2f9762876e61",
  "partialDeliveryId": "01933f...",   // optional: target one row instead of the whole order
  "amount": 49.95,                     // optional: amount to capture
  "currency": "EUR",                   // optional
  "externalReference": "ERP-DELIVERY-123",
  "payload": { }                       // optional: provider-specific hints, passed through
}
```

**Response** — the result is also written to `payment_status` / `payment_reference` on the
affected rows:

```json
{ "status": "captured", "reference": "PSP-REF-abc", "message": null }
```

`status` is one of:

| status | meaning |
|---|---|
| `captured` | The provider captured the amount. |
| `requested` | Capture was accepted/queued asynchronously by the provider. |
| `unsupported` | No handler supports this order's payment method — **nothing was charged** (graceful fallback). |
| `failed` | A handler tried and failed. See `message`. |

> Out of the box **no provider handler is registered**, so capture returns `unsupported`.
> This is intentional — see section 5 to add a provider.

### 3.5 Legacy admin endpoints (unchanged)

These power the admin "Shipments" tab and remain for backward compatibility. They keep their
original request/response shapes but now persist through the DAL internally:

| Method | Path | Purpose |
|---|---|---|
| `POST`  | `/api/_action/partial-shipment-delivery` | Create (admin grid, with placeholder rows) |
| `PATCH` | `/api/_action/partial-shipment-delivery/update/{id}` | Update one shipment |
| `POST`  | `/api/_action/partial-shipment-delivery/delete/{id}` | Delete one shipment |
| `GET`   | `/api/_action/partial-shipment-delivery/{id}` | Read one shipment |

**New integrations should use the `/api/_action/partial-delivery/*` endpoints in §3.1–3.4**,
which are cleaner, idempotent for capture, and identify line items flexibly. The legacy
routes are documented only so existing callers know they still work.

---

## 4. Using the plugin from another Shopware plugin (PHP)

The service `PartialDelivery\Service\PartialDeliveryService` is registered as **public**, so
any plugin can inject it.

```php
use PartialDelivery\Service\PartialDeliveryService;
use PartialDelivery\Payment\PartialDeliveryCaptureRequest;

public function __construct(private readonly PartialDeliveryService $partialDelivery) {}

// Create deliveries (same payload shape as the HTTP endpoint's "shipments" array)
$result = $this->partialDelivery->createShipments([
    ['orderLineItemId' => $lineItemId, 'quantity' => 2, 'trackingCode' => 'DHL-1'],
], $context, ['source' => 'my-plugin']);

// Read
$rows = $this->partialDelivery->getShipmentsByOrder($orderId, $context);

// Record a payment status yourself (e.g. from a webhook)
$this->partialDelivery->setPaymentStatus($partialDeliveryId, 'captured', 'PSP-REF-abc', $context);

// Ask the plugin to run the capture pipeline
$captureResult = $this->partialDelivery->requestCapture(
    new PartialDeliveryCaptureRequest(orderId: $orderId, amount: 49.95, currencyCode: 'EUR'),
    $context
);
```

---

## 5. Adding a payment provider

The plugin is **payment-provider agnostic**. To make partial deliveries capture money for a
specific provider, implement one interface in *your* payment plugin and tag it — no changes
to this plugin are required.

### Step 1 — implement the interface

```php
use PartialDelivery\Payment\PartialDeliveryPaymentCaptureInterface;
use PartialDelivery\Payment\PartialDeliveryCaptureRequest;
use PartialDelivery\Payment\PartialDeliveryCaptureResult;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class ExamplePaymentCaptureHandler implements PartialDeliveryPaymentCaptureInterface
{
    public function supports(OrderEntity $order, Context $context): bool
    {
        // Inspect the order's payment method handler identifier and match your provider:
        $tx = $order->getTransactions()?->last();
        $handler = $tx?->getPaymentMethod()?->getHandlerIdentifier();

        return $handler !== null && str_contains($handler, 'YourProviderPayment');
    }

    public function capture(
        OrderEntity $order,
        PartialDeliveryCaptureRequest $request,
        Context $context
    ): PartialDeliveryCaptureResult {
        // Call your provider's capture API/service here. Return one of:
        //   PartialDeliveryCaptureResult::captured($reference)
        //   PartialDeliveryCaptureResult::requested($reference)   // async
        //   PartialDeliveryCaptureResult::failed('reason')
        //   PartialDeliveryCaptureResult::unsupported('reason')   // do NOT throw for this
        return PartialDeliveryCaptureResult::captured('PSP-REF-' . $request->orderId);
    }
}
```

### Step 2 — register & tag the service

```xml
<service id="MyPaymentPlugin\ExamplePaymentCaptureHandler">
    <tag name="partial_delivery.payment_capture" priority="100"/>
</service>
```

The registry tries tagged handlers **highest priority first** and uses the first whose
`supports()` returns `true`. The bundled `NullPaymentCaptureHandler` runs last
(`priority -1000`) and guarantees a graceful `unsupported` result when no provider matches.

> **Important:** `capture()` must not throw for "this provider can't do partial capture" —
> return `unsupported()` instead. Reserve exceptions for genuinely unexpected failures.

---

## 6. Events

Subscribe to react to partial-delivery activity without modifying this plugin (useful for
ERP sync, notifications, or audit). All events are plain Symfony events.

| Event class | Dispatched when |
|---|---|
| `PartialDelivery\Event\PartialDeliveryCreatedEvent` | A partial-delivery row was stored. |
| `PartialDelivery\Event\PartialDeliveryUpdatedEvent` | A partial-delivery row was updated. |
| `PartialDelivery\Event\PartialDeliveryCaptureRequestedEvent` | Before a capture handler runs. |
| `PartialDelivery\Event\PartialDeliveryCaptureCompletedEvent` | After a capture handler runs (any outcome — check `getResult()->getStatus()`). |
| `PartialDelivery\Event\PartialDeliveryCaptureAdjustmentRequiredEvent` | An already-captured row was edited under the `flag` policy — reconcile (refund/recapture) out of band. |

```php
public static function getSubscribedEvents(): array
{
    return [PartialDeliveryCreatedEvent::class => 'onCreated'];
}

public function onCreated(PartialDeliveryCreatedEvent $event): void
{
    $delivery = $event->getPartialDelivery(); // PartialDeliveryEntity
    // push to ERP, etc.
}
```

---

## 7. Data model

Table `partial_delivery` (existing columns kept; integration columns added, all nullable):

| Column | Type | Notes |
|---|---|---|
| `id` | BINARY(16) | PK |
| `order_line_item_id` | VARCHAR(255) | The order line item this delivery belongs to |
| `quantity` | INT | Delivered quantity |
| `package` | VARCHAR(255) | Package label (may be empty for API-created rows) |
| `tracking_code` | VARCHAR(255) | Tracking code (may be empty for API-created rows) |
| `created_at` / `updated_at` | DATETIME(3) | |
| `order_id` | BINARY(16) | Order reference (set automatically on API creation) |
| `external_reference` | VARCHAR(255) | Your ERP/delivery reference |
| `source` | VARCHAR(64) | Origin marker (`external-system`, `manual`, …) |
| `payment_status` | VARCHAR(32) | `captured` / `requested` / `failed` / `unsupported` / `adjustment_required` (null = not yet captured) |
| `payment_reference` | VARCHAR(255) | Provider transaction reference |
| `amount` | DECIMAL(12,4) | Optional captured/invoiced amount |

The fields are exposed via the DAL (`PartialDeliveryEntity` getters and the
`/api/partial-delivery` admin API) for reading/filtering.

---

## 8. Error handling

| Situation | HTTP | Body |
|---|---|---|
| Malformed body / bad `shipments` | 400 | `{ "success": false, "error": "..." }` |
| Invalid order id (list/capture) | 400 | `{ "success": false, "error": "..." }` |
| All shipments invalid | 422 | `{ "success": false, "created": [], "skipped": [...] }` |
| Partial success | 201 | `{ "success": false, "created": [...], "skipped": [...] }` |
| Unexpected exception | 500 | `{ "success": false, "error": "..." }` |
| Capture, no provider | 200 | `{ "status": "unsupported", ... }` |

Per-item failures never abort the whole request — valid items are still created and the rest
are returned in `skipped` with a `reason`.

---

## 9. Notes by integrator role

- **ERP / middleware systems:** prefer identifying lines by `orderLineItemId` when you have
  it; otherwise send `orderNumber` + `productNumber`. Set `source` and `externalReference` so
  deliveries are traceable back to your system. Decide explicitly whether to call `/capture`
  after creating a delivery, or to let a Shopware-side flow do it.
- **Payment providers:** implement section 5. Until a handler is registered, `/capture` is a
  safe no-op (`unsupported`) and only records intent.
- **Implementation partners / other plugins:** you can consume the public
  `PartialDeliveryService` and the events in-process, or drive everything over the HTTP API —
  both paths are equivalent.

---

## 10. Configuration

`Settings → System → Plugins → Partial Delivery → Configure` (key
`PartialDelivery.config.captureUpdatePolicy`):

| Option | Behaviour |
|---|---|
| `protect` (default) | Reject financial edits (`quantity`/`amount`) to an already-captured delivery (`422`). Logistics fields stay editable. |
| `flag` | Allow the edit, mark the row `adjustment_required`, dispatch `PartialDeliveryCaptureAdjustmentRequiredEvent`. Never charges automatically. |

This setting only affects editing a delivery whose payment was **already captured**. Capture
itself is always idempotent regardless of this setting (it never re-charges captured rows).

---
