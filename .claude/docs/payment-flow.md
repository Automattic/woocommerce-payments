# Payment Flow — Detailed Reference

This documents the exact call chain for payment operations in WooPayments. Read this when working on payment processing, refunds, or API communication.

## Payment Intent Creation (Checkout)

### 1. Gateway Entry

**`WC_Payment_Gateway_WCPay::process_payment($order_id)`** _(called by WC Core)_
- Validates fraud prevention token via `Fraud_Prevention_Service`
- Checks rate limiter via `$this->failed_transaction_rate_limiter->is_limited()`
- Delegates to `process_payment_for_order($cart, $payment_information)`

### 2. Payment Processing

**`WC_Payment_Gateway_WCPay::process_payment_for_order()`**
- Manages customer details via `manage_customer_details_for_order()`
- Handles zero-amount orders (skips intent creation)
- Multiple paths depending on context:
  - **WooPay pre-created intents**: calls `Get_Intention::create($intent_id)->send()`
  - **Deferred intent creation** (e.g. confirmation tokens): creates `Create_Intention` first, then confirms separately
  - **Standard flow**: creates and confirms in one step via `Create_And_Confirm_Intention`

```php
// Example: the standard create-and-confirm flow (not the only path)
$request = Create_And_Confirm_Intention::create();
$request->set_amount( WC_Payments_Utils::prepare_amount( $amount, $currency ) );
$request->set_currency_code( strtolower( $currency ) );
$request->set_payment_method( $payment_method_id );  // pm_xxx or confirmation token
$request->set_customer( $customer_id );               // cus_xxx
$request->set_capture_method( $manual ? 'manual' : 'automatic' );
$request->set_metadata( $metadata );
$request->set_level3( $level3_data );
$request->set_payment_methods( $payment_methods );     // ['card'], ['card', 'link'], etc.
$response = $request->send();
```

### 3. Request Execution

**`Request::send()`** (final method, cannot be overridden)
1. Gets WordPress hook name via `$this->get_hook()` (e.g., `wcpay_create_and_confirm_intent_request`)
2. Calls `apply_filters($hook, $this)` — allows extensions to modify the request
3. Calls `$this->api_client->send_request($this)`
4. Formats response via `$this->format_response($response)`

### 4. API Client

**`WC_Payments_API_Client::send_request(Request $request)`**
- Extracts params, API path, and method from the Request object
- Delegates to `protected request($params, $api, $method, ...)`

**`WC_Payments_API_Client::request()`**
1. Applies filter `wcpay_api_request_params`
2. Builds URL: `https://public-api.wordpress.com/wpcom/v2/sites/{blog_id}/wcpay/{api}`
3. POST requests get an Idempotency-Key header (UUID)
4. Applies filter `wcpay_api_request_headers`
5. Retry loop: up to 3 retries, 250ms exponential backoff, 70s timeout
6. Calls `$this->http_client->remote_request($request_args, $body, ...)`
7. Applies filter `wcpay_api_request_response`
8. Validates response via `check_response_for_errors()`

### 5. HTTP Transport

**`WC_Payments_Http::remote_request()`**
- Validates Jetpack connection
- Injects `blog_id` into URL
- Calls `Jetpack\Connection\Client::remote_request()` — handles HTTPS and token signing

## Refund Flow

**`WC_Payment_Gateway_WCPay::process_refund($order_id, $amount, $reason)`**
- Validates order exists and payment is captured
- Gets charge ID: `$this->order_service->get_charge_id_for_order($order)`

```php
$request = Refund_Charge::create();
$request->set_charge( $charge_id );    // ch_xxx or py_xxx
$request->set_amount( WC_Payments_Utils::prepare_amount( $amount, $currency ) );
$request->set_reason( $reason );       // 'duplicate', 'fraudulent', 'requested_by_customer'
$refund = $request->send();
```

Same layers: Request → API Client → HTTP → Jetpack → wpcom → Stripe.

## Frontend Checkout (JS)

### Blocks Checkout (`client/checkout/blocks/payment-processor.js`)

1. `onPaymentSetup` hook: validates Stripe Elements, creates PaymentMethod via `stripe.createPaymentMethod({ elements })`
2. Returns `pm_xxx` ID in `meta.paymentMethodData['wcpay-payment-method']`
3. WooCommerce Blocks sends this to PHP via the Store API
4. `onCheckoutSuccess` hook: handles 3DS confirmation via `stripe.handleNextAction()` or `stripe.confirmCardPayment()`

### JS API Client (`client/checkout/api/index.js`)

- `WCPayAPI` class wraps Stripe interactions
- `getStripeForUPE(paymentMethodType)` — gets Stripe instance
- `confirmIntent(redirectUrl, shouldSavePaymentMethod)` — handles post-payment confirmation
- AJAX calls to `update_order_status` action for server-side status updates

## Data Transformations

**Amount conversion:** `WC_Payments_Utils::prepare_amount($amount, $currency)` converts decimal amounts (99.99) to cents (9999). Zero-decimal currencies (JPY) are handled automatically.

**Metadata structure:**
```php
[
    'order_id'     => $order_id,
    'order_key'    => $order->get_order_key(),
    'customer_id'  => $customer_id,
    'site_url'     => get_site_url(),
    'blog_id'      => $blog_id,
    'paid_on_woopay' => boolean,
]
```

**Level 3 data:** Extracted from order line items for corporate card interchange fee reduction. Generated by `Level3Service::get_data_from_order()`.

## Request Modification Hooks

| Hook | When | Class |
|------|------|-------|
| `wcpay_create_and_confirm_intent_request` | Before sending create+confirm intent | `Create_And_Confirm_Intention` |
| `wcpay_create_intent_request` | Before sending create intent | `Create_Intention` |
| `wcpay_get_intent_request` | Before sending get intent | `Get_Intention` |
| `wcpay_refund_charge_request` | Before sending refund | `Refund_Charge` |
| `wcpay_api_request_params` | Before building HTTP request | `WC_Payments_API_Client` |
| `wcpay_api_request_headers` | Before sending HTTP request | `WC_Payments_API_Client` |
| `wcpay_api_request_response` | After receiving HTTP response | `WC_Payments_API_Client` |

## Common Request Classes

| Class | API Path | Method | Use Case |
|-------|----------|--------|----------|
| `Create_And_Confirm_Intention` | `intentions` | POST | Standard checkout payment |
| `Create_Intention` | `intentions` | POST | Deferred confirmation (WooPay) |
| `Get_Intention` | `intentions/{id}` | GET | Retrieve intent status |
| `Capture_Intention` | `intentions/{id}/capture` | POST | Capture authorized payment |
| `Cancel_Intention` | `intentions/{id}/cancel` | POST | Cancel authorized payment |
| `Refund_Charge` | `refunds` | POST | Process refund |
| `List_Transactions` | `transactions` | GET | Admin transaction list |
| `List_Disputes` | `disputes` | GET | Admin dispute list |

## Plugin Initialization Chain

```
woocommerce-payments.php (plugin file)
  → WC_Payments::init()
    → Creates WC_Payments_API_Client (with WC_Payments_Http)
    → Creates WC_Payment_Gateway_WCPay (with api_client, account, customer_service, ...)
    → Registers gateway via 'woocommerce_payment_gateways' filter
    → Registers Blocks integration via 'woocommerce_blocks_payment_method_type_registration'
    → Creates 25+ service instances (Account, Customer, Token, Order, Webhook, etc.)
```
