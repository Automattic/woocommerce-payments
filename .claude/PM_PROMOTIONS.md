# Payment Method (PM) Promotions - Client Implementation

## Overview

PM Promotions display promotional offers for payment methods that merchants haven't yet enabled. The system uses a **flat data structure** where each promotion is a standalone object with a `type` field indicating its display context (spotlight, badge).

## Data Flow

```
Server API → Redux Store → Components
     ↓
  validate → filter → normalize
```

**Server responsibilities:**
- Provide flat array of promotions
- Handle re-show delays and dismissal limits
- Filter by locale, dismissals, activated promos

**Client responsibilities:**
- Validate promotion structure (type guards)
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

| File | Purpose |
|------|---------|
| `client/data/promotions/types.d.ts` | TypeScript interfaces |
| `client/data/promotions/hooks.ts` | `usePromotions`, `usePromotionActions` hooks |
| `client/data/promotions/selectors.ts` | Redux selectors |
| `client/data/promotions/actions.ts` | `activatePromotion`, `dismissPromotion` |
| `client/data/promotions/resolvers.ts` | API fetch with type guards |
| `client/promotions/spotlight/index.tsx` | Spotlight promotion component |

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
- `client/data/promotions/__tests__/*.test.ts`