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
 * and the UI computations driven from the same dispute. An empty string
 * (the "no product type available" case from `resolveProductType`) is
 * collapsed to `undefined` so `recordEvent` drops the property from the
 * payload rather than emitting a `''` bucket for analytics.
 */
export const getDisputeOutcomeTracksProperties = (
	dispute: ChargeDispute,
	productType: string | undefined
): {
	dispute_id: string;
	dispute_status: string;
	dispute_reason: string;
	product_type?: string;
} => ( {
	dispute_id: dispute.id,
	dispute_status: dispute.status,
	dispute_reason: dispute.reason,
	product_type: productType || undefined,
} );
