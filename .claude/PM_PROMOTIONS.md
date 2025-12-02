# Payment Method (PM) Promotions

## Overview

PM Promotions display promotional offers for payment methods that merchants haven't yet enabled. The system uses a **flat data structure** where each promotion is a standalone object with a `type` field indicating its display context (spotlight, badge).

## Data Flow

```
Server API → WC_Payments_PM_Promotions_Service → REST API → Redux Store → Components
                        ↓
              validate → filter → normalize
```

**Server responsibilities:**
- Fetch promotions from WooPayments API (with store context)
- Validate promotion structure
- Filter by: locale, dismissals, activated promos, PM validity, enabled status, **active discounts**
- Normalize data (apply fallbacks, derive titles)
- Cache results with context-aware invalidation

**Client responsibilities:**
- Validate promotion structure (type guards, defense in depth)
- Filter dismissed promotions (defense in depth)
- Render appropriate UI based on `type`
- Track analytics events

## Data Structures

### Promotion (TypeScript)

```typescript
// client/data/promotions/types.d.ts

type PromotionType = 'spotlight' | 'badge';

interface Promotion {
  id: string;                    // Unique: "{promo_id}__{type}" e.g., "klarna-2026-promo__spotlight"
  promo_id: string;              // Campaign identifier e.g., "klarna-2026-promo"
  payment_method: string;        // PM ID from Payment_Method constants e.g., "klarna"
  payment_method_title: string;  // Human-readable e.g., "Klarna" (derived server-side)
  type: PromotionType;           // Display context: 'spotlight' | 'badge'
  title: string;                 // Promotion headline
  description: string;           // Promotion body text
  cta_label: string;             // Primary button text (fallback: "Enable {payment_method_title}")
  tc_url: string;                // Terms & conditions URL (required)
  tc_label: string;              // Terms link text (fallback: "See terms")
  footnote?: string;             // Optional disclaimer text
  image?: string;                // Optional image URL
}
```

### Redux State

```typescript
interface PromotionsState {
  promotions: Promotion[];
  promotionsError?: ApiError;
}
```

### Dismissals (Server-side storage)

```php
// Flat structure: [id => timestamp]
[
  'klarna-2026-promo__spotlight' => 1733123456,
  'klarna-2026-promo__badge' => 1733123789,
]
```

## Key Files

### Client (Frontend)

| File | Purpose |
|------|---------|
| `client/data/promotions/types.d.ts` | TypeScript interfaces |
| `client/data/promotions/hooks.ts` | `usePromotions`, `usePromotionActions` hooks |
| `client/data/promotions/selectors.ts` | Redux selectors |
| `client/data/promotions/actions.ts` | `activatePromotion`, `dismissPromotion` |
| `client/data/promotions/resolvers.ts` | API fetch with type guards |
| `client/promotions/spotlight/index.tsx` | Spotlight promotion component |
| `client/components/promotional-badge/index.tsx` | Badge promotion component with T&C tooltip |
| `client/settings/payment-methods-list/payment-method.tsx` | PM settings item (uses PromotionalBadge) |

### Server (Backend)

| File | Purpose |
|------|---------|
| `includes/class-wc-payments-pm-promotions-service.php` | Main service: fetch, filter, normalize promotions |
| `includes/admin/class-wc-rest-payments-pm-promotions-controller.php` | REST API controller |
| `tests/unit/admin/test-class-wc-payments-pm-promotions-service.php` | Service unit tests |

## Hooks API

### usePromotions

```typescript
const { promotions, isLoading } = usePromotions();
// Returns: { promotions: Promotion[], isLoading: boolean }
```

### usePromotionActions

```typescript
const { activatePromotion, dismissPromotion } = usePromotionActions();

// Activate a promotion (enables the payment method)
activatePromotion(promo_id: string);  // e.g., "klarna-2026-promo"

// Dismiss a promotion
dismissPromotion(id: string);  // e.g., "klarna-2026-promo__spotlight"
```

## Selectors

```typescript
// Get all promotions
getPromotions(state): Promotion[]

// Get promotion by unique id
getPromotionById(state, id: string): Promotion | undefined

// Get promotions for a specific payment method
getPromotionsByPaymentMethod(state, paymentMethod: string): Promotion[]

// Get first promotion of a specific type
getPromotionByType(state, type: PromotionType): Promotion | undefined

// Check if promotions exist
hasPromotions(state): boolean
```

## Component Implementation Pattern

### SpotlightPromotion Example

```tsx
// client/promotions/spotlight/index.tsx

const SpotlightPromotion: React.FC = () => {
  const { promotions, isLoading } = usePromotions();
  const { activatePromotion, dismissPromotion } = usePromotionActions();

  // Check account status
  const accountStatus = window.wcpaySettings?.accountStatus?.status;
  const isAccountOnboarded = accountStatus === 'complete' || accountStatus === 'enabled';

  if (!isAccountOnboarded || isLoading) return null;
  if (!promotions?.length) return null;

  // Find spotlight promotion
  const spotlightPromotion = promotions.find(p => p.type === 'spotlight');
  if (!spotlightPromotion) return null;

  // Event handlers
  const handlePrimaryClick = () => {
    recordEvent('wcpay_payment_method_promotion_activate_click', getEventProperties());
    activatePromotion(spotlightPromotion.promo_id);
  };

  const handleDismiss = () => {
    recordEvent('wcpay_payment_method_promotion_dismiss', getEventProperties());
    dismissPromotion(spotlightPromotion.id);  // Use full id, not promo_id
  };

  return (
    <Spotlight
      heading={spotlightPromotion.title}
      description={spotlightPromotion.description}
      image={spotlightPromotion.image}
      primaryButtonLabel={spotlightPromotion.cta_label}
      onPrimaryClick={handlePrimaryClick}
      onDismiss={handleDismiss}
    />
  );
};
```

## Analytics Events

All events include base properties:

```typescript
{
  promotion_id: string,      // promo_id
  payment_method: string,    // payment_method
  id: string,                // unique id
  display_context: string,   // 'spotlight' | 'badge'
  source: string,            // page identifier
  path: string,              // window.location.pathname + search
}
```

| Event | Trigger |
|-------|---------|
| `wcpay_payment_method_promotion_view` | Promotion becomes visible |
| `wcpay_payment_method_promotion_activate_click` | Primary CTA clicked |
| `wcpay_payment_method_promotion_secondary_click` | Secondary button clicked |
| `wcpay_payment_method_promotion_dismiss` | Close/dismiss clicked |
| `wcpay_payment_method_promotion_link_click` | Terms link clicked (+ `link_type: 'terms'`) |

## REST API Endpoints

### GET /wc/v3/payments/pm-promotions

Returns array of visible promotions (already filtered server-side).

### POST /wc/v3/payments/pm-promotions/{identifier}/activate

Activates a promotion (enables the payment method).

**Body:**
```json
{ "accept_terms": true }
```

### POST /wc/v3/payments/pm-promotions/{identifier}/dismiss

Dismisses a promotion.

**Body:**
```json
{ "id": "klarna-2026-promo__spotlight" }
```

## Type Guards (Validation)

The resolver validates API responses:

```typescript
function isPromotion(value: unknown): value is Promotion {
  if (typeof value !== 'object' || value === null) return false;
  const obj = value as Record<string, unknown>;
  return (
    typeof obj.id === 'string' &&
    typeof obj.promo_id === 'string' &&
    typeof obj.payment_method === 'string' &&
    typeof obj.payment_method_title === 'string' &&
    typeof obj.type === 'string' &&
    (obj.type === 'spotlight' || obj.type === 'badge') &&
    typeof obj.title === 'string' &&
    typeof obj.description === 'string' &&
    typeof obj.cta_label === 'string' &&
    typeof obj.tc_url === 'string' &&
    typeof obj.tc_label === 'string'
  );
}
```

## Important Implementation Notes

1. **ID vs promo_id**: Use `id` for dismissals, `promo_id` for activation
2. **Account check**: Only show promotions when `accountStatus.status` is 'complete' or 'enabled'
3. **Type filtering**: Each component filters for its own `type` ('spotlight', 'badge')
4. **No variations**: The client receives flat promotions - no nested structures
5. **Server derives titles**: `payment_method_title` comes from server, not client lookup
6. **Fallbacks applied server-side**: `cta_label` and `tc_label` have server-side defaults
7. **Image is optional**: Don't display image section if `image` is empty/undefined

## Testing

### Mock Promotion Data

```typescript
const mockPromotion = {
  id: 'klarna-promo__spotlight',
  promo_id: 'klarna-promo',
  payment_method: 'klarna',
  payment_method_title: 'Klarna',
  type: 'spotlight',
  title: 'Zero Processing Fees for 90 Days',
  description: 'Save on every Klarna transaction.',
  cta_label: 'Enable Klarna',
  tc_url: 'https://example.com/terms',
  tc_label: 'See terms',
  footnote: '*Offer valid for new activations only.',
  image: 'https://example.com/image.png',
};
```

### Test Files

- `client/promotions/spotlight/__tests__/index.test.tsx`
- `client/components/promotional-badge/__tests__/index.test.tsx`
- `client/data/promotions/__tests__/*.test.ts`
- `tests/unit/admin/test-class-wc-payments-pm-promotions-service.php`

---

## Server-Side Implementation

### WC_Payments_PM_Promotions_Service

The main service class handles fetching, filtering, and normalizing promotions.

#### Dependencies

```php
// Constructor accepts optional dependencies for testing
public function __construct( $gateway = null, $account = null ) {
    $this->gateway = $gateway;  // WC_Payment_Gateway_WCPay
    $this->account = $account;  // WC_Payments_Account
}
```

If not provided, dependencies are lazily resolved via `WC_Payments::get_gateway()` and `WC_Payments::get_account_service()`.

#### Key Methods

| Method | Purpose |
|--------|---------|
| `get_visible_promotions()` | Main entry point - returns filtered, normalized promotions |
| `activate_promotion($identifier, $accept_terms)` | Activate a promotion (enable PM) |
| `dismiss_promotion($id)` | Dismiss a promotion |
| `clear_cache()` | Clear the promotions transient cache |

#### Filtering Logic (`filter_promotions`)

Promotions are filtered out if:
1. **Invalid PM**: Payment method not in `get_upe_available_payment_methods()`
2. **Already enabled**: Payment method already in `get_upe_enabled_payment_method_ids()`
3. **Has active discount**: Payment method has an existing discount (see below)
4. **Different promo_id**: Only the first `promo_id` per payment method is kept

```php
private function filter_promotions( array $promotions ): array {
    foreach ( $promotions as $promotion ) {
        // Skip invalid PMs
        if ( ! $this->is_valid_payment_method( $pm_id ) ) continue;

        // Skip already enabled PMs
        if ( in_array( $pm_id, $enabled_pms, true ) ) continue;

        // Skip PMs with active discounts
        if ( $this->payment_method_has_discount( $pm_id ) ) continue;

        // Keep only first promo_id per PM
        // ...
    }
}
```

#### Caching

- Cache key: `wcpay_pm_promotions` (transient)
- Cache invalidation: Context hash based on dismissals + locale
- Error caching: API errors cached for 6 hours to prevent hammering

---

## Account Fees & Discount Detection

### Fee Structure

Account fees are retrieved via `WC_Payments_Account::get_fees()` and indexed by payment method ID:

```php
$fees = [
    'klarna' => [
        'base' => [
            'percentage_rate' => 0.029,
            'fixed_rate' => 30,
            'currency' => 'usd',
        ],
        'discount' => [
            [
                'discount' => 50,           // Percentage off (50 = 50% off)
                'end_time' => 1735689600,   // Unix timestamp
                'volume_currency' => 'usd',
                'volume_allowance' => 100000, // Optional: volume limit in cents
            ],
        ],
    ],
    'card' => [
        'base' => [ /* ... */ ],
        // No 'discount' key = no active discount
    ],
];
```

### Discount Detection

A payment method has an active discount if:
1. `fees[pm_id]['discount']` exists and is an array
2. `fees[pm_id]['discount'][0]['discount']` is non-empty (truthy)

```php
private function payment_method_has_discount( string $payment_method_id ): bool {
    $fees = $this->get_account_fees();

    if ( empty( $fees[ $payment_method_id ] ) ) {
        return false;
    }

    $pm_fees = $fees[ $payment_method_id ];

    if ( ! empty( $pm_fees['discount'] ) && is_array( $pm_fees['discount'] ) ) {
        $first_discount = $pm_fees['discount'][0] ?? [];
        return ! empty( $first_discount['discount'] );
    }

    return false;
}
```

**Important**: Promotions are filtered out for payment methods with active discounts to prevent showing promotional offers when the merchant already has a discount applied.

---

## PromotionalBadge Component

The `PromotionalBadge` component displays badge-type promotions in the payment methods settings list.

### Props

```typescript
interface PromotionalBadgeProps {
    message: string;      // Badge text (e.g., "Zero fees for 90 days")
    tooltip: string;      // Tooltip content
    type?: ChipType;      // Visual style (default: 'success')
    tooltipLabel?: string; // Accessible label for tooltip button
    tcUrl?: string;       // Optional T&C URL - appends link to tooltip
    tcLabel?: string;     // Optional T&C link text (fallback: "See terms and conditions")
}
```

### T&C Link Behavior

When `tcUrl` is provided:
1. A link is appended to the tooltip content
2. If `tcLabel` is provided and non-empty, it's used as the link text
3. Otherwise, falls back to "See terms and conditions"
4. Link opens in new tab with `rel="noopener noreferrer"`

```tsx
// Use backend-provided tc_label when available, otherwise fall back to default.
const tcLinkLabel = tcLabel || __( 'See terms and conditions', 'woocommerce-payments' );

// Build tooltip content with optional T&C link.
const tooltipContent = tcUrl ? (
    <>
        { tooltip }{ ' ' }
        <a href={ tcUrl } target="_blank" rel="noopener noreferrer">
            { tcLinkLabel }
        </a>
    </>
) : (
    tooltip
);
```

### Usage in PaymentMethod Component

The `PaymentMethod` component (`payment-method.tsx`) shows badges for:
1. **Active discounts** from account fees (via `accountFees[id].discount`)
2. **Badge-type promotions** from the promotions API

```tsx
// Get badge-type promotion for this payment method.
const { promotions = [] } = usePromotions();
const badgePromotion = promotions?.find(
    ( promo ) => promo.payment_method === id && promo.type === 'badge'
);

// Priority: active discount > badge promotion
const showPromotionalBadge = hasDiscount || badgePromotion;

// Get badge content from appropriate source
if ( hasDiscount ) {
    return {
        message: getDiscountBadgeText( discountFee ),
        tooltip: getDiscountTooltipText( discountFee ),
        tooltipLabel: __( 'Discount details', 'woocommerce-payments' ),
    };
}
if ( badgePromotion ) {
    return {
        message: badgePromotion.title,
        tooltip: badgePromotion.description,
        tooltipLabel: __( 'Promotion details', 'woocommerce-payments' ),
        tcUrl: badgePromotion.tc_url,
        tcLabel: badgePromotion.tc_label,
    };
}
```

---

## Storage Keys

| Option/Transient | Purpose |
|------------------|---------|
| `wcpay_pm_promotions` | Transient cache for promotions |
| `_wcpay_pm_promotion_dismissals` | Option: [id => timestamp] |
| `_wcpay_activated_pm_promotions` | Option: [identifier => timestamp] |