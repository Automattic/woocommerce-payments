# Dispute Evidence System — Architecture Reference

**Last updated:** 2026-04-15

Reference for the dispute challenge UI in `client/disputes/new-evidence/`. Covers how recommended document fields are resolved, how the evidence matrix is structured, how the cover letter stays in sync with the form, and how to add a new reason × product type combination.

## Key Files

| File | Purpose |
|---|---|
| `client/disputes/new-evidence/index.tsx` | Main component — manages challenge flow, state, and cover letter generation |
| `client/disputes/new-evidence/cover-letter-generator.ts` | Generates the cover letter body and attachment list |
| `client/disputes/new-evidence/recommended-document-fields.ts` | Resolves which fields to show (matrix path or legacy fallback) |
| `client/disputes/new-evidence/evidence-matrix.ts` | Matrix data: `[reason][productType] → fields` |
| `client/disputes/new-evidence/document-field-keys.ts` | Constants mapping semantic names to Stripe API evidence keys |
| `client/disputes/new-evidence/shipping-utils.ts` | Determines whether the shipping step should appear |
| `client/disputes/new-evidence/types.ts` | TypeScript types (`RecommendedDocument`, etc.) |

## Product Types

The dropdown in `product-details.tsx` renders a different set of options depending on the additional evidence types feature flag:

**When the flag is ON** (matrix-driven form):

- `physical_product` — physical products (may require shipping)
- `digital_product_or_service` — digital products
- `offline_service` — offline services
- `booking_reservation` — bookings and reservations
- `event` — events
- `other` — generic catch-all

**When the flag is OFF** (legacy form):

- `physical_product`, `digital_product_or_service`, `offline_service`, plus `multiple` ("Multiple product types") for mixed-product orders.

`multiple` is not a dropdown option when the flag is ON, but the backend can still return `suggested_product_type = 'multiple'` for mixed-product orders, and saved drafts can carry that value in `__product_type` metadata. On first load, `index.tsx` normalizes `'multiple'` to `'other'` when the flag is enabled so the dropdown selects "Other" rather than rendering in an invalid-selected state. The legacy path (flag OFF) still honors `'multiple'` — there is a `subscription_canceled × multiple` matrix entry that remains reachable only from the legacy form.

## Document Field Keys

Defined in `document-field-keys.ts`. These map to Stripe API evidence fields:

- `receipt`, `customer_communication`, `customer_signature`, `refund_policy`
- `duplicate_charge_documentation`, `shipping_documentation`, `service_documentation`
- `cancellation_policy`, `cancellation_rebuttal`, `access_activity_log`, `uncategorized_file`

`REFUND_RECEIPT_DOCUMENTATION` is an alias for `duplicate_charge_documentation` (used in duplicate disputes where a refund receipt is needed).

## Two-Tier Field Resolution

`getRecommendedDocumentFields()` in `recommended-document-fields.ts` orchestrates field resolution. Signature:

```typescript
getRecommendedDocumentFields(
    reason,
    refundStatus?,
    duplicateStatus?,
    productType?,
    enhancedEligibilityTypes?   // e.g. ['visa_compliance']
)
```

Flow:

```
  Feature flag ON?
    ├─ No  → Legacy: orderedFields + reasonSpecificFields[reason]
    │
    └─ Yes
        ├─ Visa Compliance dispute?
        │     (reason is 'noncompliant' OR enhancedEligibilityTypes includes 'visa_compliance')
        │   └─ Short-circuit: return a fixed two-field set
        │      (CUSTOMER_COMMUNICATION "Upload evidence" + UNCATEGORIZED_FILE "Other documents")
        │
        ├─ Derive status from refundStatus / duplicateStatus
        │   (credit_not_processed → refundStatus, duplicate → duplicateStatus)
        │
        └─ getMatrixFields(reason, effectiveProductType, status)
            ├─ Match        → matrix fields + auto-merged base "Customer communication" field
            └─ No match     → fall back to legacy fields
```

The feature flag is `_wcpay_feature_dispute_additional_evidence_types` (frontend key `isDisputeAdditionalEvidenceTypesEnabled`). It gates the matrix path and acts as a rollback escape hatch — when disabled, the legacy fallback path runs unconditionally. The legacy path stays in the codebase until the flag is removed.

### Base Field Auto-Merge

When the matrix path is used, a base **"Customer communication"** field (key: `customer_communication`, order: `20`) is automatically merged into every matrix entry — **unless** the matrix entry already contains a field with `key === DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION`.

This is the key to the **`CUSTOMER_COMMUNICATION` repurposing pattern**: to prevent auto-merge of the base field, include `CUSTOMER_COMMUNICATION` in the matrix entry with a different label (e.g. "Other documents"). This effectively "claims" the slot.

### Field Ordering Mechanics

Every matrix field has an `order` value that controls its position in the UI. The auto-merged base "Customer communication" has `order: 20`. Slot fields before and after it using order values:

```
order: 10  → Receipt (before Customer communication)
order: 15  → Prior undisputed transaction history
order: 20  → Customer communication (auto-merged base field)
order: 25  → Customer's signature
order: 30  → Refund policy
order: 100 → Other documents (always last)
```

When designing a new matrix entry, choose `order` values relative to `20` to control placement around the auto-merged base field.

## Evidence Matrix Structure

### Standard Reasons

For most reasons, the matrix is a simple two-level lookup: `evidenceMatrix[reason][productType]`.

```typescript
export const evidenceMatrix = {
    fraudulent: {
        booking_reservation: [ /* fields */ ],
        physical_product: [ /* fields */ ],
        // ...
    },
    product_not_received: {
        booking_reservation: [ /* fields */ ],
        // ...
    },
};
```

### Status-Dependent Reasons

`duplicate` and `credit_not_processed` have an additional status dimension. They use **composite keys**: `${productType}__${status}`.

```typescript
evidenceMatrix['duplicate']['booking_reservation__is_duplicate']
evidenceMatrix['duplicate']['booking_reservation__is_not_duplicate']
evidenceMatrix['credit_not_processed']['booking_reservation__refund_has_been_issued']
evidenceMatrix['credit_not_processed']['booking_reservation__refund_was_not_owed']
```

`getMatrixFields()` handles the dispatch:

```typescript
getMatrixFields(reason, productType, status)
// duplicate / credit_not_processed: look up evidenceMatrix[reason][`${productType}__${status}`]
// all other reasons:                look up evidenceMatrix[reason][productType]
```

A status-less lookup for a status-dependent reason returns `undefined` — the caller is expected to supply a status.

## Adding a New Reason × Product Type Combination

### 1. Evidence Matrix (`evidence-matrix.ts`)

Add an entry to the relevant reason's getter function:

```typescript
// Standard reason (e.g. getFraudulentMatrix)
booking_reservation: [
    { key: DOCUMENT_FIELD_KEYS.FIELD_NAME, label: __('Label'), description: __('Desc'), order: 10 },
    // ...
],

// Status-dependent reason (e.g. getCreditNotProcessedMatrix)
booking_reservation__refund_has_been_issued: [
    { key: DOCUMENT_FIELD_KEYS.RECEIPT, label: __('Refund receipt'), description: __('...'), order: 10 },
    // ...
],
```

To prevent the base "Customer communication" from being auto-merged, include `CUSTOMER_COMMUNICATION` in the entry with a custom label:

```typescript
{
    key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
    label: __('Other documents'),
    description: __('Any other relevant documents that will support your case.'),
    order: 100,
},
```

### 2. Cover Letter Generator (`cover-letter-generator.ts`)

Not every new matrix entry requires cover letter changes. The generator uses `onlyForReasons` and `onlyForProductTypes` filters in `standardAttachments` that often already cover new combinations. Always check the existing `standardAttachments` rules before assuming changes are needed.

`standardAttachments` properties:

| Property | Purpose |
|---|---|
| `key` | Document field key |
| `label` | Default label in cover letter |
| `onlyForReasons` | Only include for these dispute reasons |
| `onlyForProductTypes` | Only include for these product types |
| `excludeWhen` | Function to conditionally exclude (receives `reason`, optional `status`) |
| `labelForReasons` | Override label for specific reason + product type + refund status combos |
| `labelForStatus` | Override label when `duplicateStatus` matches a given value — `{ status, label }` |
| `orderForReasons` | Override sort order for specific reason + product type + refund status combos |

`labelForReasons` and `orderForReasons` share the same filter shape:

```typescript
labelForReasons: [
    {
        reasons: ['credit_not_processed'],           // required
        label: __('Refund receipt'),                  // (or `order: N` for orderForReasons)
        productTypes: ['booking_reservation'],       // optional
        refundStatuses: ['refund_has_been_issued'],  // optional
    },
],
```

Supported filter keys: `reasons`, `productTypes`, `refundStatuses`. Duplicate-status-dependent labels are handled separately by the top-level `labelForStatus` property, not by `labelForReasons`:

```typescript
labelForStatus: {
    status: 'is_duplicate',
    label: __('Refund receipt'),
},
```

### 3. Shipping Logic (`shipping-utils.ts`)

The shipping step appears only when:

- Product type is `physical_product`, AND
- Reason is NOT in `ReasonsNoShipping` (`duplicate`, `subscription_canceled`, `credit_not_processed`).

### 4. Tests to Update

When adding a new combination, update:

1. **`evidence-matrix-spec-validation.test.ts`** — add an entry to `implementedCombinations` with UI fields, expected labels, excluded fields, and cover letter attachments.
2. **`cover-letter-generator.test.ts`** — add an attachment ordering test.
3. **`recommended-document-fields.test.ts`** — add or update a test for the new matrix entry.

> When `CUSTOMER_COMMUNICATION` keeps its default label (not repurposed), add "Customer communication" to both `uiFields.shouldInclude` AND `coverLetterAttachments.shouldInclude`. It's easy to forget the cover-letter side because the field isn't in the matrix entry — it's added by auto-merge logic at runtime.

> `coverLetterAttachments.shouldExclude` must only list labels that are filtered out by `onlyForReasons`, `onlyForProductTypes`, or `excludeWhen` — NOT labels that merely aren't in the matrix. The spec validation test populates evidence for ALL Stripe fields, so unfiltered fields like `RECEIPT` ("Order receipt"), `REFUND_POLICY` ("Refund policy"), and `SHIPPING_DOCUMENTATION` ("Proof of shipping") appear in the cover letter for ALL product types regardless of the matrix. Only `CUSTOMER_SIGNATURE` ("Customer's signature") is reliably excluded for non-physical product types via `onlyForProductTypes: ['physical_product']`.

## Cover Letter Attachment Ordering

The `standardAttachments` array index is the default sort order. If a field appears in a different position in the UI (per the evidence matrix) than its array index, the cover letter will list it in the wrong order — this is the most common bug in this area.

The fix: use `orderForReasons` to override the sort order for specific reason × product type combinations. Values are **relative to other items' array indices**.

An index map comment at the top of `standardAttachments` documents the mapping:

```
//   0: RECEIPT, 1: DUPLICATE_CHARGE_DOCUMENTATION, 2: ACCESS_ACTIVITY_LOG (fraudulent),
//   3: CUSTOMER_COMMUNICATION, 4: CUSTOMER_SIGNATURE, 5: REFUND_POLICY,
//   6: SHIPPING_DOCUMENTATION, 7: SERVICE_DOCUMENTATION, 8: ACCESS_ACTIVITY_LOG (non-fraudulent),
//   9: CANCELLATION_REBUTTAL, 10: CANCELLATION_POLICY, 11: UNCATEGORIZED_FILE
```

Example: for `subscription_canceled`, the UI shows Cancellation logs (index 9) before Refund policy (index 5). Fix: give `REFUND_POLICY` `order: 10` for `subscription_canceled`, pushing it after index 9.

Ties are broken by array index (stable sort), so `order: 5` on `CUSTOMER_COMMUNICATION` (index 3) places it after `CUSTOMER_SIGNATURE` (index 4) but before `REFUND_POLICY` (index 5) because 3 < 5.

### Verifying Cover Letter Ordering

To systematically verify that the cover letter order matches the form for a given reason × product type:

1. **List the form fields** from the evidence matrix entry, in `order` value sequence (including auto-merged `CC` at order 20 if not explicitly present).
2. **For each form field**, find its corresponding entry in `standardAttachments` and resolve:
   - `sortOrder` = `orderForReasons` match → explicit `order` → array index (default)
   - `displayLabel` = `labelForStatus` → `labelForReasons` match → default `label`
3. **Sort** resolved attachments by `sortOrder`, ties broken by `arrayIndex`.
4. **Compare** the sorted cover letter sequence against the form field sequence — they must match.

## Stripe Field Repurposing Pattern

When the spec requires more conceptual documents than available Stripe fields, a single Stripe field can serve different purposes across different reason × product type combinations by using `labelForReasons` + `orderForReasons`.

**Example: `SERVICE_DOCUMENTATION` triple duty**

| Reason × Product Type | Label | Purpose |
|---|---|---|
| `product_unacceptable` × `physical_product` | "Item's condition" | Default meaning |
| `product_unacceptable` × `digital_product_or_service` | "Proof of delivered service" | Digital delivery proof |
| `fraudulent` × `digital_product_or_service` | "Prior undisputed transaction history" | Repurposed because `ACCESS_ACTIVITY_LOG` is already used for "Login or usage records" |
| `credit_not_processed` × `physical_product` (`refund_was_not_owed`) | "Other documents" | Repurposed because `UNCATEGORIZED_FILE` is used for "Proof of acceptance" |

> When repurposing a field, always add both `labelForReasons` AND `orderForReasons`. A repurposed field inherits its array index position, which likely doesn't match the desired form order. Forgetting the `orderForReasons` entry is the most common cause of cover letter ordering bugs.

## Critical Behaviors

### Cover Letter Regeneration

- Cover letter regenerates when `productType` changes (resets `isCoverLetterManuallyEdited` flag).
- Evidence is filtered by `getRecommendedDocumentFields()` before generating the cover letter.
- Only documents for fields visible in the current UI appear in the cover letter.

### Evidence Visibility Filtering

When regenerating the cover letter, evidence in fields NOT recommended by the current matrix/product type is zeroed out via `getApplicableEvidence()` (see `index.tsx`). Files remain in Stripe but won't appear in the cover letter if the new matrix doesn't recommend that field for the current reason × product type. This is the most likely source of "missing evidence" reports.

### State Management

- When product type changes to a non-shipping type, shipping documentation is cleared from state.
- Document field values persist in `evidence` state even if the field isn't visible in the UI.
- The cover letter filters evidence at generation time, not in state.

## Testing

### Local Dev Testing Tips

Use the **WCPay Dev Tools** plugin to override the dispute reason for testing different dispute types without needing actual disputes of each type:

1. Go to **WCPay Dev** in the WP admin sidebar.
2. Check "Enable dispute reason override".
3. Select the desired dispute reason (e.g. `credit_not_processed`).
4. Save Changes.
5. Navigate to any dispute's challenge page — it will use the overridden reason.

### Testing Checklist

1. **Test both directions** — switch from product type A → B and B → A.
2. **Test with uploaded documents** — upload docs, then switch product type.
3. **Verify cover letter** — check attachment labels match the current product type and follow the same order as the form.
4. **Check shipping step visibility** — only for `physical_product` + non-excluded reasons.
5. **Test save/reload** — ensure evidence persists correctly after page reload.
6. **Test status switching** — for `duplicate` and `credit_not_processed`, switch between status options and verify fields update.
7. **Verify base field merge** — check that "Customer communication" appears when expected and doesn't when repurposed.
