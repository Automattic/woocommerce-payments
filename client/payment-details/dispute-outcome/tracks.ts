/** @format **/

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';

/**
 * Base property bag shared by every Outcome View Tracks event.
 * Mirrors the getDisputeTracksProperties pattern used elsewhere for
 * dispute Tracks events so event payloads stay consistent across the
 * dispute funnel.
 */
export const getDisputeOutcomeTracksProperties = (
	dispute: ChargeDispute
): {
	dispute_id: string;
	dispute_status: string;
	dispute_reason: string;
	product_type: string;
} => ( {
	dispute_id: dispute.id,
	dispute_status: dispute.status,
	dispute_reason: dispute.reason,
	product_type: resolveProductType(
		dispute.metadata,
		dispute.order?.suggested_product_type,
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ??
			false
	),
} );
