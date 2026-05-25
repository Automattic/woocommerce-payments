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
 * De-dup is module-scoped, not a component ref, because the payment-details
 * loading lifecycle mounts DisputeOutcomeView several times per view (and
 * before the id resolves); a per-instance ref resets on each mount and would
 * let the event fire 2-6x. The absent-id case is skipped so a view is never
 * recorded without a dispute_id.
 *
 * `productType` is passed in (not resolved here) so the Tracks payload and the
 * caller's UI share one resolveProductType() result and can't drift.
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
 * Test-only: clears the de-dup memory between cases.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention
export const _resetOutcomeViewTrackingForTests = (): void => {
	seenDisputeIds.clear();
};
