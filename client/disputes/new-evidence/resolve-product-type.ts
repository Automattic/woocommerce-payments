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
 */
export const resolveProductType = (
	metadata: Record< string, unknown > | null | undefined,
	suggestedProductType: string | null | undefined,
	isAdditionalEvidenceTypesEnabled: boolean
): string => {
	const raw =
		( metadata?.__product_type as string | undefined ) ||
		suggestedProductType ||
		'';
	return isAdditionalEvidenceTypesEnabled && raw === 'multiple'
		? 'other'
		: raw;
};
