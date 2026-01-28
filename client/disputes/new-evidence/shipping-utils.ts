/**
 * Dispute reasons that don't require shipping information.
 */
export const ReasonsNoShipping = [
	'duplicate',
	'subscription_canceled',
	'credit_not_processed',
];

/**
 * Determines if shipping information is needed for a dispute.
 *
 * Shipping is only required when:
 * 1. The product type is 'physical_product'
 * 2. The dispute reason is not in the ReasonsNoShipping list
 *
 * @param reason - The dispute reason
 * @param productType - The product type (defaults to empty string)
 * @return true if shipping information is needed, false otherwise
 */
export function needsShipping(
	reason: string | undefined,
	productType = ''
): boolean {
	// Only physical products need shipping
	if ( productType !== 'physical_product' ) return false;
	// Check dispute reason logic
	if ( ReasonsNoShipping.includes( reason || '' ) ) return false;
	return true;
}
