/** @format **/

/**
 * Internal dependencies
 */
import { recordEvent } from 'wcpay/tracks';
import type { ChargeDispute } from 'wcpay/types/charges';

const seenDisputeIds = new Set< string >();

/**
 * Records the Outcome View event once per dispute per page session.
 *
 * De-dup is module-scoped, not a component ref: the payment-details loading
 * lifecycle remounts DisputeOutcomeView several times per view, so a
 * per-instance ref would reset and re-fire.
 *
 * productType is passed in (not resolved here) so it can't drift from the
 * value the caller renders.
 */
export const recordOutcomeViewOnce = (
	dispute: ChargeDispute,
	productType: string | undefined
): void => {
	if ( ! dispute.id || seenDisputeIds.has( dispute.id ) ) {
		return;
	}
	recordEvent( 'wcpay_dispute_outcome_viewed', {
		dispute_id: dispute.id,
		dispute_status: dispute.status,
		dispute_reason: dispute.reason,
		// Omit the key when unknown so analytics never see a '' bucket.
		...( productType ? { product_type: productType } : {} ),
	} );
	seenDisputeIds.add( dispute.id );
};

/**
 * Test-only: clears the de-dup memory.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention
export const _resetOutcomeViewTrackingForTests = (): void => {
	seenDisputeIds.clear();
};
