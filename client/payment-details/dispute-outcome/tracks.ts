/** @format **/

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';

/**
 * Base property bag shared by every Outcome View Tracks event.
 *
 * `productType` is passed in (not resolved here) so the Tracks payload and
 * the caller's UI share one resolveProductType() result and can't drift.
 */
export const getDisputeOutcomeTracksProperties = (
	dispute: ChargeDispute,
	productType: string | undefined
): {
	dispute_id: string;
	dispute_status: ChargeDispute[ 'status' ];
	dispute_reason: ChargeDispute[ 'reason' ];
	product_type?: string;
} => ( {
	dispute_id: dispute.id,
	dispute_status: dispute.status,
	dispute_reason: dispute.reason,
	// Omit the key when unknown so analytics never see a '' bucket.
	...( productType ? { product_type: productType } : {} ),
} );
