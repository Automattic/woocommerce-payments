/** @format **/

/**
 * Resolves a dispute's product type the same way the pre-response wizard and
 * the post-resolution Outcome View both read it, so they look up the same
 * matrix cell for a given dispute.
 *
 * Source priority:
 *   1. `metadata.__product_type` — written by the wizard on save.
 *   2. `order.suggested_product_type` — backend default for the order.
 *
 * Normalization: when the additional-evidence-types flag is on, `'multiple'`
 * is no longer selectable in the wizard's product-type dropdown
 * (`product-details.tsx`), so legacy values coming back from the backend or
 * from older drafts are coerced to `'other'` to avoid an
 * unselected-dropdown / inconsistent-matrix-cell state.
 *
 * Accepts an explicit `{ __product_type?: string }` shape rather than the
 * looser `Record<string, unknown>` so the metadata read doesn't require a
 * cast; matches the `Dispute['metadata']` type in `types/disputes.d.ts`.
 */
export const resolveProductType = (
	metadata:
		| {
				/* eslint-disable-next-line @typescript-eslint/naming-convention -- Stripe metadata key; leading underscores are part of the wire format. */
				__product_type?: string;
		  }
		| null
		| undefined,
	suggestedProductType: string | null | undefined,
	isAdditionalEvidenceTypesEnabled: boolean
): string => {
	const raw = metadata?.__product_type || suggestedProductType || '';
	return isAdditionalEvidenceTypesEnabled && raw === 'multiple'
		? 'other'
		: raw;
};
