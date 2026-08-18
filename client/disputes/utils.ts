/**
 * External dependencies
 */

import moment from 'moment';

/**
 * Internal dependencies
 */
import type {
	CachedDispute,
	Dispute,
	DisputeStatus,
	EvidenceDetails,
} from 'wcpay/types/disputes';
import type { BalanceTransaction } from 'wcpay/types/balance-transactions';
import type { Charge } from 'wcpay/types/charges';
import { getChargeDisputes } from 'wcpay/utils/charge';
import { formatStringValue } from 'wcpay/utils';
import {
	disputeAwaitingResponseStatuses,
	disputeUnderReviewStatuses,
} from 'wcpay/disputes/filters/config';
import {
	formatCurrency,
	formatExplicitCurrency,
} from 'multi-currency/interface/functions';
import { klarnaChargebackLossReasons } from 'wcpay/disputes/strings';

interface IsDueWithinProps {
	dueBy: CachedDispute[ 'due_by' ] | EvidenceDetails[ 'due_by' ];
	days: number;
}
/**
 * Returns true if a dispute due_by date is within the specified number of days.
 * Returns false if the dispute due_by date is not within the specified number of days
 * or if the due_by value is not a valid date.
 *
 * @param {IsDueWithinProps} props       - An object containing function arguments.
 * @param {number}           props.dueBy - The dispute due_by date. Accepts a unix timestamp {@link EvidenceDetails} or a date string {@link CachedDispute}.
 * @param {number}           props.days  - The number of days to check.
 *
 * @return {boolean} True if the dispute is due within the specified number of days.
 */
export const isDueWithin = ( { dueBy, days }: IsDueWithinProps ): boolean => {
	if ( ! dueBy ) {
		return false;
	}

	// Parse the due by date. If it's a number, it's a unix timestamp.
	const dueByMoment =
		typeof dueBy === 'number'
			? moment.unix( dueBy as number )
			: moment.utc( dueBy as string, true );

	if ( ! dueByMoment.isValid() ) {
		// If we can't parse the date, we assume it's not urgent.
		return false;
	}

	const now = moment().utc();
	const isWithinDays = dueByMoment.diff( now, 'days', true ) <= days;
	const isPastDue = now.isAfter( dueByMoment );
	return isWithinDays && ! isPastDue;
};

export const isAwaitingResponse = (
	status: DisputeStatus | string
): boolean => {
	return disputeAwaitingResponseStatuses.includes( status );
};

export const isUnderReview = ( status: DisputeStatus | string ): boolean => {
	return disputeUnderReviewStatuses.includes( status );
};

export const isInquiry = ( status: DisputeStatus ): boolean => {
	// Inquiry dispute statuses are one of `warning_needs_response`, `warning_under_review` or `warning_closed`.
	return status.startsWith( 'warning' );
};

export const isRefundable = ( status: DisputeStatus ): boolean => {
	// Refundable dispute statuses are one of `warning_needs_response`, `warning_under_review`, `warning_closed` or `won`.
	return isInquiry( status ) || status === 'won';
};

/**
 * Returns true if a dispute is a Visa compliance dispute.
 * A dispute is considered a Visa compliance dispute if:
 * - The reason is 'noncompliant', OR
 * - The enhanced_eligibility_types includes 'visa_compliance'
 *
 * @param {Pick<Dispute, 'reason' | 'enhanced_eligibility_types'>} dispute - The dispute object.
 * @return {boolean} True if the dispute is a Visa compliance dispute.
 */
export const isVisaComplianceDispute = (
	dispute: Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >
): boolean => {
	if ( ! dispute ) {
		return false;
	}

	return (
		dispute.reason === 'noncompliant' ||
		( dispute.enhanced_eligibility_types || [] ).includes(
			'visa_compliance'
		)
	);
};

/**
 * What Klarna said about a chargeback it ruled against the merchant.
 *
 * - `stated`      — Klarna gave a reason, ready to display.
 * - `unspecified` — Klarna closed the dispute without giving one. This is a
 *   fact worth telling the merchant, so it stays distinct from "we don't know".
 */
export type KlarnaLossReason =
	| { type: 'stated'; display: string }
	| { type: 'unspecified' };

/**
 * Returns Klarna's stated reason for ruling against the merchant, if there is one.
 *
 * Klarna is the only payment method that reports a loss reason, and it only does
 * so once it closes a chargeback. Returns `null` when there's nothing to say:
 * a non-Klarna dispute, or a Klarna dispute Stripe hasn't annotated (yet).
 *
 * @param {Pick<Dispute, 'payment_method_details'>} dispute - The dispute object.
 * @return {KlarnaLossReason | null} The loss reason, or null if none is available.
 */
export const getKlarnaLossReason = (
	dispute: Pick< Dispute, 'payment_method_details' >
): KlarnaLossReason | null => {
	const code =
		dispute?.payment_method_details?.klarna?.chargeback_loss_reason_code
			?.trim()
			.toLowerCase()
			.replace( /[\s-]+/g, '_' );

	if ( ! code ) {
		return null;
	}

	if ( code === 'reason_unspecified' ) {
		return { type: 'unspecified' };
	}

	// Codes read as English phrases, so humanizing an unmapped one still tells
	// the merchant more than hiding it would. Translated once it's in the map.
	const display =
		klarnaChargebackLossReasons[ code ] ?? formatStringValue( code );

	return { type: 'stated', display };
};

/**
 * Returns Klarna's loss reasons for a charge's disputes, keyed by dispute id.
 *
 * The timeline's `dispute_lost` events carry no loss reason of their own, so the
 * timeline reads them off the charge instead. Only disputes Klarna gave a reason
 * for appear here.
 *
 * @param {Charge} charge - The charge to collect loss reasons from.
 * @return {Record<string, KlarnaLossReason>} Loss reasons keyed by dispute id.
 */
export const getKlarnaLossReasons = (
	charge: Charge
): Record< string, KlarnaLossReason > => {
	const reasonById: Record< string, KlarnaLossReason > = {};

	getChargeDisputes( charge ).forEach( ( dispute ) => {
		const lossReason = getKlarnaLossReason( dispute );
		if ( lossReason ) {
			reasonById[ dispute.id ] = lossReason;
		}
	} );

	return reasonById;
};

/**
 * Returns the dispute fee balance transaction for a dispute if it exists
 * and the deduction has not been reversed.
 *
 * Legacy-only implementation: the "fee reversed when dispute_reversal row
 * exists" rule is business logic the server should own. Prefer reading
 * `dispute.effective_fee` (set by Disputes_Controller) via
 * `getDisputeFeeFormatted` — this helper is retained for consumers that
 * pre-date the server annotation.
 */
const getDisputeDeductedBalanceTransaction = (
	dispute: Pick< Dispute, 'balance_transactions' >
): BalanceTransaction | undefined => {
	const disputeFee = dispute.balance_transactions.find(
		( transaction ) => transaction.reporting_category === 'dispute'
	);

	const disputeFeeReversal = dispute.balance_transactions.find(
		( transaction ) => transaction.reporting_category === 'dispute_reversal'
	);

	if ( disputeFeeReversal ) {
		return undefined;
	}

	return disputeFee;
};

/**
 * Returns the effective dispute fee as a raw `{ amount, currency }` pair if it
 * exists and the deduction has not been reversed.
 *
 * Prefers the server-computed `dispute.effective_fee` when present.
 * Falls back to inspecting `balance_transactions` directly for responses
 * from older servers that don't emit the annotation.
 *
 * Callers that need the number (e.g. summing across a charge's disputes)
 * use this; `getDisputeFeeFormatted` formats the same value for display.
 */
export const getDisputeFeeAmount = (
	dispute: Pick< Dispute, 'balance_transactions' | 'effective_fee' >
): { amount: number; currency: string } | undefined => {
	// Server-computed path: effective_fee is explicitly null when the fee
	// was reversed, an object when it's still effective.
	if ( dispute.effective_fee !== undefined ) {
		if ( dispute.effective_fee === null ) {
			return undefined;
		}
		return {
			amount: dispute.effective_fee.amount,
			currency: dispute.effective_fee.currency,
		};
	}

	// Legacy fallback.
	const disputeFee = getDisputeDeductedBalanceTransaction( dispute );
	if ( ! disputeFee ) {
		return undefined;
	}
	return { amount: disputeFee.fee, currency: disputeFee.currency };
};

/**
 * Returns the dispute fee formatted as a currency string if it exists
 * and the deduction has not been reversed.
 */
export const getDisputeFeeFormatted = (
	dispute: Pick< Dispute, 'balance_transactions' | 'effective_fee' >,
	appendCurrencyCode?: boolean
): string | undefined => {
	const disputeFee = getDisputeFeeAmount( dispute );
	if ( ! disputeFee ) {
		return undefined;
	}
	return appendCurrencyCode
		? formatExplicitCurrency( disputeFee.amount, disputeFee.currency )
		: formatCurrency( disputeFee.amount, disputeFee.currency );
};
