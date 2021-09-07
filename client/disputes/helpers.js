export const PRODUCT_TYPE_META_KEY = '__product_type';

/**
 * Retrieves product type from the dispute.
 *
 * @param {Object?} dispute Dispute object
 * @return {string} dispute product type
 */
export const getDisputeProductType = ( dispute ) => {
	if ( ! dispute ) {
		return '';
	}

	let productType = dispute?.metadata?.[ PRODUCT_TYPE_META_KEY ] ?? '';

	// Fallback to `multiple` when evidence submitted but no product type meta.
	if (
		! productType &&
		dispute?.evidence_details &&
		dispute?.evidence_details?.has_evidence
	) {
		productType = 'multiple';
	}

	return productType;
};
