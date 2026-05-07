/**
 * Internal dependencies
 */
import type { DisputeReason, ProductType } from 'wcpay/types/disputes';
import { emptyByProductType } from './high-impact-fields';

/**
 * Topical recommendations the Outcome View surfaces as `optional_missing`
 * (muted), but the wizard intentionally omits.
 *
 * Lives separately from `evidenceMatrix` so post-resolution coaching doesn't
 * bleed into the pre-response wizard surface.
 *
 * Promotion to `DISPUTE_HIGH_IMPACT_FIELDS` is a pure data move when a
 * Q-refresh shows a lift signal of at least +3pp.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const DISPUTE_TOPICAL_FIELDS: Record<
	DisputeReason,
	Record< ProductType, string[] >
> = {
	subscription_canceled: {
		// physical_product, multiple: refund_policy is already in the
		// wizard matrix for these cells; no need to duplicate.
		physical_product: [],
		digital_product_or_service: [ 'refund_policy' ],
		offline_service: [ 'refund_policy' ],
		event: [ 'refund_policy' ],
		booking_reservation: [ 'refund_policy' ],
		multiple: [],
		other: [ 'refund_policy' ],
	},
	product_unacceptable: {
		// physical, digital, offline, event, booking: refund_policy is
		// already in the wizard matrix for these cells.
		physical_product: [],
		digital_product_or_service: [],
		offline_service: [],
		event: [],
		booking_reservation: [],
		multiple: [],
		other: [ 'refund_policy' ],
	},
	// All other reasons: topicals are already covered by the wizard
	// matrix for the relevant product types, so no entries are needed
	// here.
	bank_cannot_process: emptyByProductType(),
	check_returned: emptyByProductType(),
	credit_not_processed: emptyByProductType(),
	customer_initiated: emptyByProductType(),
	debit_not_authorized: emptyByProductType(),
	duplicate: emptyByProductType(),
	fraudulent: emptyByProductType(),
	general: emptyByProductType(),
	incorrect_account_details: emptyByProductType(),
	insufficient_funds: emptyByProductType(),
	noncompliant: emptyByProductType(),
	product_not_received: emptyByProductType(),
	unrecognized: emptyByProductType(),
};
