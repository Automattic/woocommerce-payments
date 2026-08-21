# Payment Method Lifecycle — Reference

**Last updated:** 2026-08-21

This documents how a payment method comes to be offered at checkout: the two independent pieces of state involved, where each lives, and what mutates them. Read this when working on the settings payment-methods list, `upe_enabled_payment_method_ids`, capability statuses, onboarding payment-method selection, or any "why isn't this method showing up?" investigation.

## Key Fact: Two Independent Booleans

Almost every bug in this area comes from conflating two separate pieces of state:

| | **Permission** | **Merchant intent** |
|---|---|---|
| Question | *May* this account charge with this method? | *Should* this store offer it? |
| Owner | Stripe, gated by Transact (see below) | The WC store |
| Storage | Stripe account capability, keyed `<x>_payments` | Gateway option `upe_enabled_payment_method_ids` |
| Values | `active` / `pending` / `inactive` / `unrequested` (plus values Transact synthesizes — see below) | id present in the array, or absent |
| Mutated by | Stripe review; capability requests | Merchant toggling in settings |

They are joined by `$payment_method_capability_key_map` in `includes/class-wc-payment-gateway-wcpay.php`, which maps internal id → capability key (`klarna` → `klarna_payments`, `apple_pay` → `card_payments`).

**Checkout requires both.** Neither implies the other. A method is commonly `active` on the account while switched off in the store.

## The Three Layers

This plugin never talks to Stripe directly. Every capability read and write goes through the Transact platform, which hosts the `/wcpay/*` endpoints this plugin calls and holds the platform Stripe account that the merchant's Express account is connected to.

| Layer | Owns | Decides |
|---|---|---|
| **WC store** (this plugin) | `upe_enabled_payment_method_ids`, per-gateway `enabled`, the cached copy of account data | Which methods the merchant wants offered, and all checkout-time filtering (currency, amount limits, subscription context, manual capture) |
| **Transact** | The payment method availability rules, the fee structures, and the account response the plugin caches | Which methods are available for a given country and client version, whether a method is available to a particular account, and how Stripe's raw statuses are reported |
| **Stripe** | The Express account's capabilities | Whether the account is actually permitted to charge with a method — `active`, `pending`, `inactive` |

The practical consequence: a status the plugin reads is Transact's *account* of what Stripe says, and an enable request is a request to Transact, which may or may not reach Stripe. Both are elaborated below.

## Terminology Trap

"Capability" means two unrelated things in this codebase:

- **Stripe capability** — `card_payments`, `klarna_payments`. The permission above.
- **Payment method capability** — `PaymentMethodCapability::TOKENIZATION`, `REFUNDS`, `BUY_NOW_PAY_LATER`, `CAPTURE_LATER`, `MULTI_CURRENCY`, `DOMESTIC_TRANSACTIONS_ONLY`, `EXPRESS_CHECKOUT` (`includes/payment-methods/Configs/Constants/`). A static, code-only description of what a method can do. Never leaves the plugin.

`KlarnaDefinition::get_capabilities()` returns the second kind; `KlarnaDefinition::get_stripe_id()` returns the first kind's key.

## Where Client State Lives

**Definitions** (`includes/payment-methods/Configs/Definitions/`) — one class per method implementing `PaymentMethodDefinitionInterface`: internal id, Stripe capability key (`get_stripe_id()`, default `<id>_payments`), supported currencies/countries, per-currency amount limits. Registered by the hardcoded list in `PaymentMethodDefinitionRegistry` — 17 methods, plus Amazon Pay behind a feature flag.

**Merchant intent** — `upe_enabled_payment_method_ids`, default `['card']`. Duplicated into every gateway instance's settings and re-synced on every write.

**Per-gateway `enabled` flag** — `WC_Payments::init()` builds one `WC_Payment_Gateway_WCPay` *per payment method* (a card gateway plus a split gateway for each other method), each with its own standard WC `enabled` option toggled via `enable()`/`disable()`. Redundant with the list above, but both are load-bearing: WC core's `WC_Payment_Gateway::is_available()` reads this one. Note `register_gateway()` registers card once and skips Link entirely.

**Cached permission state** — `wcpay_account_data` (`Database_Cache::ACCOUNT_KEY`) holds `capabilities` and `capability_requirements`. TTL **2h in admin, 24h front-end**; failed fetches back off progressively (2/5/10/15 min). Read via `get_upe_enabled_payment_method_statuses()`, which falls back to a synthetic `card_payments: active` when the cache holds no capabilities at all.

## Status Vocabulary

The status the plugin sees is **not** raw Stripe. Transact rewrites it on every account read:

| Status | Origin |
|---|---|
| `active`, `pending`, `unrequested` | Stripe, passed through |
| `inactive` | Stripe, when not rewritten below |
| `pending_verification` | **Transact** — Stripe `inactive` + disabled reason `requirements.fields_needed` |
| `rejected` | **Transact** — Stripe `inactive` + disabled reason `rejected.*` (clients ≥ 6.8.0) |
| `disabled` | **Transact** — capability is `active` but the method is switched off platform-wide |

Independently of Stripe's status, **Transact may report a method as unavailable** — either platform-wide or for one account. The two produce different symptoms, worth telling apart when debugging:

| What Transact reports | What the plugin sees |
|---|---|
| Method unavailable platform-wide | Status rewritten to `disabled` (the capability may still be `active` on Stripe) |
| Method unavailable for this account | Entry **absent from the response**, which the plugin reads as `unrequested` |

The second case also affects writes: an enable request for a method Transact reports as unavailable for the account is refused without reaching Stripe (see the enable path below).

Client-side constants live in `client/settings/constants.js` (`upeCapabilityStatuses`). Note that `disabled` is absent from them — see the known gap under Settings UI Semantics.

## Read Path

```
Stripe account capabilities
  → Transact: rewrites statuses, applies availability rules
    → GET /wcpay/accounts → capabilities, capability_requirements
      → Database_Cache::ACCOUNT_KEY (2h admin / 24h front-end)
        → $gateway->get_upe_enabled_payment_method_statuses()
```

`account.updated` webhooks call `refresh_account_data()`, so status changes normally land without waiting for the TTL.

## Checkout Gate

`get_payment_method_ids_enabled_at_checkout()` — a method appears only if **all** hold:

1. present in `upe_enabled_payment_method_ids`;
2. survives the manual-capture filter (when manual capture is on: card and Link only);
3. `is_enabled_at_checkout()` — subscription-context compatible, order total within the method's per-currency limits;
4. currency valid for the account's domestic currency (skipped in admin unless forced);
5. **capability status is exactly `active`**;
6. Link is dropped when card is not also enabled.

`is_available()` on each gateway then adds: master gateway enabled, HTTPS in live mode, the gateway's own `enabled === 'yes'`, express-checkout placement rules, and Afterpay/Affirm shipping-address requirements on order-pay.

## Write Path — Enabling

`PUT /wc/v3/payments/settings` → `update_enabled_payment_methods()` in `includes/admin/class-wc-rest-payments-settings-controller.php`:

1. **Manual-capture filter**, when manual capture is being enabled in the same request — drops every method that does not allow manual capture; Link is always kept, since it follows card's capture behaviour.
2. **Filter to available.** Intersects the result with `get_upe_available_payment_methods()` = registered definitions ∩ methods the account has **fees** for. Fees are composed by Transact and arrive in the cached account data — there is no local fee table. Apple Pay and Google Pay borrow card's fee entry. A method with no fee entry does not appear as available, which is what shapes the settings list. Treat this as a **client-side filter, not an enforcement boundary** — it runs in the plugin and is filterable via `wcpay_upe_available_payment_methods`. Enforcement is server-side and independent: Transact validates every capability request (step 3), and the checkout gate requires an `active` capability that only Stripe grants. Bypassing the filter locally cannot make a method chargeable.
3. **`request_unrequested_payment_methods()`** — for each method whose cached status is `unrequested` *or absent*, POST to the Transact `accounts/capabilities` endpoint with `requested: true`. Absent is treated as unrequested deliberately: a never-requested capability simply does not appear in the account payload.
4. Refresh the account cache if any request changed a status.
5. Tracks events for each added and removed method. Then, for each newly enabled method, promotion activation followed by `enable()` on its split gateway; for each removed method, `disable()` on its split gateway.
6. Write `upe_enabled_payment_method_ids` to **every** gateway instance.

**What Transact does with step 3.** It validates the capability against its own whitelist (an unknown capability is a 400, never forwarded), then checks availability for the account. If Transact reports the method as unavailable there, it synthesizes a refusal **without contacting Stripe at all**, which the plugin renders exactly as it renders a Stripe-side refusal. Otherwise it requests the capability on the connected Express account and invalidates its own account cache so the next read is fresh.

A newly requested capability usually returns `pending`, not `active` — enabling in settings frequently does **not** make the method live immediately.

## Write Path — Disabling

Tracks event → `disable()` on the split gateway → remove the id from `upe_enabled_payment_method_ids` and re-sync all gateways.

**Nothing is sent to Transact or Stripe.** `WC_Payments_API_Client::request_capability()` has exactly one caller and it always passes `true`; there is no unrequest path anywhere in the plugin. The onboarding payload builder carries the same rule explicitly ("We only request, not unrequest capabilities").

Consequences:

- A Stripe account's capability set is a **high-water mark of everything ever enabled**, not a picture of what the store currently offers. Never read capabilities to infer current checkout configuration.
- Re-enabling is instant — the capability is usually still `active`, so step 3 above skips it entirely.
- `requested` is permanent, but `active` is not: Stripe can move a capability to `inactive` or `rejected` at any time, including while the method sits disabled and unwatched.

## Onboarding

- **Account creation** — Transact creates the Stripe account with `card_payments` and `transfers` requested (plus US tax reporting), merging in whatever capabilities the plugin sent. The plugin does not request these itself.
- **NOX preselection**: WooCommerce core sends a complete `{ payment_method: true|false }` map. `WC_Payments_Onboarding_Service::get_account_data()` requests only the `true` entries; `false` entries are **omitted, not unrequested**. For a new account the two are equivalent, since every capability starts `unrequested`.
- `update_enabled_payment_methods_ids()` **merges** into the existing list rather than replacing it, and enables each method's split gateway. Link and WooPay are mutually exclusive: when both would end up enabled, Link wins and WooPay stays off (see #9404).
- **Test-drive accounts** — Transact creates a *custom* Stripe account (not Express) and requests the capability of every method available for onboarding in that country and client version, ignoring the merchant's selection — so a test-drive account is not representative of what a live one will have. Converting to live deletes that account and creates a fresh one, so nothing carries over implicitly.
- **Account reset / `account.deleted`** returns the list to `['card']`.

## Settings UI Semantics

`client/settings/payment-methods-list/use-payment-method-availability.tsx` maps status to merchant-facing state. Anything other than `active`/`unrequested` blocks the toggle:

| Status | Chip | Toggle |
|---|---|---|
| `active` | — | operable |
| `unrequested` (or absent) | — | operable; toggling on triggers the capability request |
| `pending` | "Approval pending" | blocked (Alipay/WeChat get a 2–3 day approval note) |
| `pending_verification` | "Pending verification" | blocked; links to Payments overview |
| `inactive` | "More information needed" | blocked |
| `rejected` | "Rejected" (alert) | blocked; contact support |

**Known gap:** `disabled` is not handled anywhere in the client. It matches none of the branches above, so the settings UI renders the method as fully operable with no notice, while the checkout gate (which requires exactly `active`) silently drops it. A merchant in that state can toggle the method on and see no effect and no explanation.

## Special Cases

- **Apple Pay / Google Pay** — no Stripe capability of their own; availability rides on card. But the two halves of the client disagree on *which key* says so. The PHP key map points both at `card_payments`, which is what the checkout gate and the capability request use. Their definition classes, however, derive `apple_pay_payments` / `google_pay_payments` through the default `<id>_payments` rule, and that is the `stripe_key` the settings UI looks up — a key the account payload never carries, so the UI always falls back to `unrequested` and renders the toggle as operable regardless of card's real status. Placement in the methods list vs. as express buttons is `express_checkout_in_payment_methods`; they enter the available list by borrowing card's fee entry.
- **Link** — has a real `link_payments` capability, but is filtered out of checkout when card is not enabled, is never registered as a WC gateway, and is mutually exclusive with WooPay.
- **WooPay** — not a Stripe capability; a separate `platform_checkout` gateway option.
- **Stale key-map entries** — `sofort`, `giropay`, `jcb` remain in `$payment_method_capability_key_map` with no corresponding definition class.

## Common Failure Modes

| Symptom | Likely cause |
|---|---|
| Method enabled in settings, absent at checkout | Capability not `active` (often `pending` right after enabling), or currency/amount limits, or manual capture filtering it out |
| Toggle does nothing, no notice shown | Status is `disabled` — see the known gap above |
| Apple Pay / Google Pay toggle operable while card is `pending` or `inactive` | Settings UI looks up `apple_pay_payments` / `google_pay_payments`, keys the account never carries, and defaults to `unrequested`; the checkout gate uses `card_payments` — see Special Cases |
| Method missing from the settings list entirely | No fee entry for it on the account, so it never reaches `get_upe_available_payment_methods()` |
| Method reappears as `pending_verification` after months disabled | Requirements lapsed while it sat unused; the capability stayed requested the whole time |
| Settings and checkout disagree after a country switch | `get_settings()` intersects the stored list with currently-available methods on read; invalid entries are hidden, not deleted |
| Stale status after a Stripe-side change | Account cache TTL (2h admin / 24h front-end); an `account.updated` webhook normally refreshes it sooner |

## Related Docs

- `.claude/docs/pm-promotions.md` — promotional offers for not-yet-enabled methods. Activating a promotion also requests the underlying capability.
- `.claude/docs/payment-flow.md` — how a payment executes once a method is offered.
- `.claude/docs/capital-flow.md` — same account-cache authority pattern, for Capital.
