/** @format **/

/**
 * Returns the dispute's product type using the same precedence and
 * normalization the response wizard applies, so the Outcome View looks up
 * the same matrix cell.
 *
 * Source priority:
 *   1. `metadata.__product_type` — written by the wizard on save.
 *   2. `order.suggested_product_type` — backend default for the order.
 *
 * When the additional-evidence-types flag is on, legacy `'multiple'` values
 * are coerced to `'other'` (the wizard removed `'multiple'` from its
 * dropdown in `product-details.tsx`).
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
