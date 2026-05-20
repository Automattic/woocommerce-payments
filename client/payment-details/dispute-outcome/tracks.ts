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

const seenDisputeIds = new Set< string >();

/**
 * Records that the Outcome View for `disputeId` has been seen and returns
 * whether this was the first time this page session. Module-scoped, not a
 * component ref, because the payment-details loading lifecycle mounts
 * DisputeOutcomeView several times per view (and before the id resolves); a
 * per-instance ref resets on each mount and would let the event fire 2-6x.
 * Also filters out the absent-id case so a view is never recorded without a
 * dispute_id. Result: one event per dispute per page session.
 */
export const registerOutcomeViewSeen = ( disputeId?: string ): boolean => {
	if ( ! disputeId || seenDisputeIds.has( disputeId ) ) {
		return false;
	}
	seenDisputeIds.add( disputeId );
	return true;
};

/**
 * Test-only: clears the de-dup memory between cases.
 */
export const resetOutcomeViewTrackingForTests = (): void => {
	seenDisputeIds.clear();
};
