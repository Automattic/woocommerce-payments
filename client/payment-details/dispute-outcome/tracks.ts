/** @format **/

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';

/**
 * Base property bag shared by every Outcome View Tracks event.
 * Mirrors the getDisputeTracksProperties pattern used elsewhere for
 * dispute Tracks events so event payloads stay consistent across the
 * dispute funnel.
 *
 * `productType` is passed in (rather than resolved here) so callers can
 * share a single `resolveProductType()` result between the Tracks payload
 * and the UI computations driven from the same dispute. When it is empty
 * (the "no product type available" case from `resolveProductType`) the
 * `product_type` key is omitted entirely, so analytics never see a `''`
 * bucket.
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
	// Genuinely omit the key when there is no product type, rather than
	// emitting product_type: undefined. Keeps the payload self-describing
	// instead of leaning on recordEvent's undefined-stripping.
	...( productType ? { product_type: productType } : {} ),
} );
