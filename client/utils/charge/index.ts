/** @format **/

/**
 * External dependencies
 */
import { sumBy } from 'lodash';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Dispute } from 'types/disputes';
import {
	Charge,
	ChargeAmounts,
	ChargeDispute,
	ChargeRefund,
} from 'types/charges';
import { PaymentIntent } from '../../types/payment-intents';

// Prefer the array when the server sends it — it's authoritative and already
// includes the singular dispute. Fall back to `charge.dispute` for payloads
// without the array: responses from before the companion server change
// deploys, and any where the server omits it (e.g. its List Disputes call
// failed), so a single dispute still renders.
export const getChargeDisputes = ( charge: Charge ): ChargeDispute[] => {
	if ( charge.disputes?.length ) {
		return charge.disputes;
	}
	return charge.dispute ? [ charge.dispute ] : [];
};

export interface DisputeOrder {
	orderById: Record< string, number >;
	total: number;
}

// Numbering the disputes by their creation time (not array order) is what lets
// the summary panes and the timeline agree on "Dispute N of M": both derive the
// ordinal from this single mapping keyed by dispute id, so the oldest dispute is
// always "Dispute 1" wherever it appears. `created` can be missing on some
// payloads; when it is, the sort leaves those entries in their original
// relative position, so the result is still deterministic.
export const getDisputeOrdinals = ( charge: Charge ): DisputeOrder => {
	const disputes = [ ...getChargeDisputes( charge ) ].sort(
		( a, b ) => ( a.created ?? 0 ) - ( b.created ?? 0 )
	);

	const orderById: Record< string, number > = {};
	disputes.forEach( ( dispute, index ) => {
		orderById[ dispute.id ] = index + 1;
	} );

	return { orderById, total: disputes.length };
};

const failedOutcomeTypes = [ 'issuer_declined', 'invalid' ];
const blockedOutcomeTypes = [ 'blocked' ];
const unsuccessfulRefundStatuses = [ 'failed', 'canceled' ];

const isUnsuccessfulRefund = ( refund: { status?: string | null } ): boolean =>
	unsuccessfulRefundStatuses.includes( refund.status ?? '' );

// Capturing less than was authorized makes Stripe release the remainder as a
// refund on the charge: it counts towards `amount_refunded` and shows up in
// `refunds`, but nobody refunded anything. Its balance transaction is the only
// field that tells it apart from a refund the merchant issued.
export const isPartialCaptureReversal = ( refund: ChargeRefund ): boolean =>
	refund.balance_transaction?.reporting_category ===
	'partial_capture_reversal';

const getMerchantRefunds = ( charge: Charge ): ChargeRefund[] =>
	( charge.refunds?.data ?? [] ).filter(
		( refund ) => ! isPartialCaptureReversal( refund )
	);

/**
 * The part of the authorization that was released rather than captured, in
 * the charge currency. Derived from the charge amounts rather than from
 * `refunds`, which may be missing or truncated to its first page.
 */
export const getUncapturedAmount = (
	charge: Charge = < Charge >{}
): number => {
	if ( ! charge.captured || typeof charge.amount_captured !== 'number' ) {
		return 0;
	}
	return Math.max( 0, charge.amount - charge.amount_captured );
};

/**
 * `amount_refunded` without the released remainder of a partial capture, in
 * the charge currency.
 */
export const getChargeRefundedAmount = (
	charge: Charge = < Charge >{}
): number =>
	Math.max(
		0,
		( charge.amount_refunded ?? 0 ) - getUncapturedAmount( charge )
	);

/**
 * Settlement-currency amount released by partial-capture reversals, as a
 * positive number. Only reversals listed in `refunds` count, so it is 0 when
 * the list doesn't carry them.
 */
const getPartialCaptureReversalBalanceAmount = ( charge: Charge ): number =>
	-sumBy(
		( charge.refunds?.data ?? [] ).filter( isPartialCaptureReversal ),
		'balance_transaction.amount'
	);

/**
 * IDs of the balance transactions that released the uncaptured remainder of
 * the charge, for telling them apart from refunds in the timeline.
 */
export const getPartialCaptureReversalTransactionIds = (
	charge: Charge = < Charge >{}
): string[] =>
	( charge.refunds?.data ?? [] )
		.filter( isPartialCaptureReversal )
		.map( ( refund ) => refund.balance_transaction?.id )
		.filter( ( id ): id is string => !! id );

export const getDisputeStatus = (
	dispute: null | Pick< Dispute, 'status' > = < Dispute >{}
): string => dispute?.status || '';

export const getChargeOutcomeType = (
	charge: Charge = < Charge >{}
): string => ( charge.outcome ? charge.outcome.type : '' );

export const isChargeSuccessful = ( charge: Charge = < Charge >{} ): boolean =>
	charge.status === 'succeeded' && charge.paid === true;

export const isChargeFailed = ( charge: Charge = < Charge >{} ): boolean =>
	charge.status === 'failed' &&
	failedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeBlocked = ( charge: Charge = < Charge >{} ): boolean =>
	charge.status === 'failed' &&
	blockedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeCaptured = ( charge: Charge = < Charge >{} ): boolean =>
	charge.captured === true;

export const isChargeDisputed = ( charge: Charge = < Charge >{} ): boolean =>
	charge.disputed === true;

export const isChargeRefunded = ( charge: Charge = < Charge >{} ): boolean =>
	getChargeRefundedAmount( charge ) > 0;

export const isChargeRefundFailed = (
	charge: Charge = < Charge >{}
): boolean => {
	const refunds = getMerchantRefunds( charge );
	return (
		charge.refunded === false &&
		refunds.length > 0 &&
		refunds.every( isUnsuccessfulRefund )
	);
};

export const isChargeFullyRefunded = (
	charge: Charge = < Charge >{}
): boolean => charge.refunded === true;

export const isChargePartiallyRefunded = (
	charge: Charge = < Charge >{}
): boolean => isChargeRefunded( charge ) && ! isChargeFullyRefunded( charge );

const getFraudMetaBoxType = (
	charge?: Charge,
	paymentIntent?: PaymentIntent
): string =>
	charge?.order?.fraud_meta_box_type ||
	paymentIntent?.order?.fraud_meta_box_type ||
	'';

export const isOnHoldByFraudTools = (
	charge?: Charge,
	paymentIntent?: PaymentIntent
): boolean => {
	const fraudMetaBoxType = getFraudMetaBoxType( charge, paymentIntent );

	if ( ! fraudMetaBoxType ) {
		return false;
	}

	return (
		paymentIntent?.status === 'requires_capture' &&
		fraudMetaBoxType === 'review'
	);
};

export const isBlockedByFraudTools = (
	charge?: Charge,
	paymentIntent?: PaymentIntent
): boolean => {
	const fraudMetaBoxType = getFraudMetaBoxType( charge, paymentIntent );

	if ( ! fraudMetaBoxType ) {
		return false;
	}

	return [ 'block', 'review_blocked' ].includes( fraudMetaBoxType );
};

const getPaymentIntentDerivedStatus = (
	paymentIntent?: PaymentIntent
): string | undefined =>
	paymentIntent?.status === 'requires_capture' ? 'authorized' : undefined;

export const getChargeStatus = (
	charge: Charge = < Charge >{},
	paymentIntent?: PaymentIntent
): string => {
	if ( isOnHoldByFraudTools( charge, paymentIntent ) ) {
		return 'fraud_outcome_review';
	}

	if ( isBlockedByFraudTools( charge, paymentIntent ) ) {
		return 'fraud_outcome_block';
	}

	if ( isChargeFailed( charge ) ) {
		return 'failed';
	}
	if ( isChargeBlocked( charge ) ) {
		return 'blocked';
	}
	if ( isChargeDisputed( charge ) ) {
		return 'disputed_' + getDisputeStatus( charge.dispute );
	}
	if ( isChargeRefundFailed( charge ) ) {
		return 'refund_failed';
	}
	if ( isChargePartiallyRefunded( charge ) ) {
		return 'refunded_partial';
	}
	if ( isChargeFullyRefunded( charge ) ) {
		return 'refunded_full';
	}
	const paymentIntentStatus = getPaymentIntentDerivedStatus( paymentIntent );
	if ( paymentIntentStatus ) {
		return paymentIntentStatus;
	}
	if ( isChargeSuccessful( charge ) ) {
		return isChargeCaptured( charge ) ? 'paid' : 'authorized';
	}
	return charge.status;
};

/**
 * Envelope fee totals can't be trusted if its declared currency disagrees
 * with the charge's balance_transaction — a mismatch could shift amounts
 * by 100× via zero-decimal currency rules, so callers fall back to legacy
 * fields. Missing `balance_transaction.currency` is not a mismatch; the
 * envelope's own currency stands.
 */
export const canUseFeeBreakdownData = ( charge: Charge ): boolean => {
	const feeTotal = charge.fee_breakdown_v1?.totals?.fee;
	if ( ! feeTotal ) {
		return false;
	}

	const chargeCurrency = charge.balance_transaction?.currency;
	if ( ! chargeCurrency ) {
		return true;
	}

	return feeTotal.currency.toLowerCase() === chargeCurrency.toLowerCase();
};

/**
 * Calculates display values for charge amounts in settlement currency.
 *
 * @param {Charge} charge The full charge object.
 * @return {ChargeAmounts} An object, containing the `currency`, `amount`, `net`, `fee`, and `refunded` amounts in Stripe format (*100).
 */
export const getChargeAmounts = ( charge: Charge ): ChargeAmounts => {
	// FEE_BREAKDOWN_FORK_PATCH: remove the legacy branch when the envelope
	// is the only path. The two branches answer the same question from
	// different sources — the envelope reports the charge's current state,
	// the legacy path reconstructs it from balance transactions — and they
	// derive opposite fields: the envelope is handed `net` and works out
	// `refunded`, the legacy path accumulates `refunded` and works out
	// `net`. A change to either needs checking against the other.
	//
	// Both paths report a partially captured charge at the captured amount:
	// the released remainder is taken off `amount` rather than reported as
	// `refunded`, as nothing was refunded.
	const releasedBalanceAmount =
		getPartialCaptureReversalBalanceAmount( charge );
	const breakdown = charge.fee_breakdown_v1;
	if (
		canUseFeeBreakdownData( charge ) &&
		breakdown?.totals?.net &&
		breakdown.totals.gross
	) {
		// Envelope is authoritative for the charge's *current* state: the
		// server has already folded customer-refunds, dispute fees, and
		// dispute balance adjustments into `totals.fee` / `totals.net`, so
		// there is no client-side subtraction here. `refunded` is derived
		// for backward compatibility with consumers that still read it.
		//
		// `totalFee` has to be the full Stripe deduction — fee plus tax,
		// not `totals.fee.amount` alone — because it is both what we report
		// as the fee and what gets subtracted to derive `refunded`. Dropping
		// the tax would understate the first and overstate the second.
		// Prefer the server-pre-summed `fee_plus_tax`; fall back to
		// fee + tax for older servers.
		const totalFee =
			breakdown.totals.fee_plus_tax?.amount ??
			breakdown.totals.fee.amount + ( breakdown.totals.tax?.amount ?? 0 );
		const withdrawn =
			breakdown.totals.gross.amount -
			breakdown.totals.net.amount -
			totalFee;
		// While `gross` is the charge's balance transaction, it still holds
		// the released remainder that `net` already excludes, so move it out
		// of the withdrawn amount. A `gross` reported at the captured amount
		// no longer matches that transaction and needs no correction.
		const released =
			breakdown.totals.gross.amount === charge.balance_transaction?.amount
				? releasedBalanceAmount
				: 0;
		return {
			currency: breakdown.totals.fee.currency.toLowerCase(),
			amount: breakdown.totals.gross.amount - released,
			fee: totalFee,
			net: breakdown.totals.net.amount,
			refunded: withdrawn - released,
		};
	}

	// Legacy fallback path: the envelope is absent, currencies mismatch,
	// or the charge predates the envelope rollout. Reconstruct net the
	// old way, absorbing customer refunds and dispute adjustments here.
	const balance = charge.balance_transaction
		? {
				currency: charge.balance_transaction.currency,
				amount:
					charge.balance_transaction.amount - releasedBalanceAmount,
				fee: charge.balance_transaction.fee,
				refunded: 0,
				net: 0,
			}
		: {
				currency: charge.currency,
				amount: charge.amount - getUncapturedAmount( charge ),
				fee: charge.application_fee_amount,
				refunded: 0,
				net: 0,
			};

	if ( isChargeRefunded( charge ) ) {
		balance.refunded -= sumBy(
			getMerchantRefunds( charge ).filter(
				( refund ) => ! isUnsuccessfulRefund( refund )
			),
			'balance_transaction.amount'
		);
	}

	if ( isChargeDisputed( charge ) ) {
		// A charge can carry more than one dispute; fold every dispute's
		// balance transactions into the fee/refund totals so a second dispute
		// isn't silently dropped from `Total fees` on this fallback path.
		const disputeBalanceTransactions = getChargeDisputes( charge ).flatMap(
			( dispute ) => dispute.balance_transactions ?? []
		);
		balance.fee += sumBy( disputeBalanceTransactions, 'fee' );
		balance.refunded -= sumBy( disputeBalanceTransactions, 'amount' );
	}

	balance.net = balance.amount - balance.fee - balance.refunded;

	return balance;
};

/**
 * Displays the transaction's sales channel: Online store | In-Person | In-Person (POS).
 * This method is called on the list of transactions page.
 *
 * In the list of transactions, the type holds the brand of the payment method, so we aren't passing it.
 * Instead, we pass the transaction.channel directly, which might be in_person|in_person_pos|online.
 *
 * @param {string} channel The transaction channel.
 * @return {string} Online store, In-Person, or In-Person (POS).
 */
export const getTransactionChannel = ( channel: string ): string => {
	switch ( channel ) {
		case 'in_person':
			return __( 'In-Person', 'woocommerce-payments' );
		case 'in_person_pos':
			return __( 'In-Person (POS)', 'woocommerce-payments' );
		default:
			return __( 'Online store', 'woocommerce-payments' );
	}
};

/**
 * Displays the sales channel based on the charge data from Stripe and metadata for a transaction: Online store | In-Person | In-Person (POS).
 * This method is called in the individual transaction page.
 *
 * In the individual transaction page, we are getting the data from Stripe, so we pass the charge.type
 * which can be card_present or interac_present for In-Person payments. In addition, we pass the transaction metadata
 * whose ipp_channel value can be mobile_store_management or mobile_pos that indicates whether the channel is from store
 * management or POS in the mobile apps.
 *
 * @param {string}              type     The transaction charge type, which can be card_present or interac_present for In-Person payments.
 * @param {Record<string, any>} metadata The transaction metadata, which may include ipp_channel indicating the channel source.
 * @return {string} Returns 'Online store', 'In-Person', or 'In-Person (POS)' based on the transaction type and metadata.
 */
export const getChargeChannel = (
	type: string,
	metadata: Record< string, any >
): string => {
	if ( type === 'card_present' || type === 'interac_present' ) {
		if ( metadata?.ipp_channel === 'mobile_pos' ) {
			return __( 'In-Person (POS)', 'woocommerce-payments' );
		}
		return __( 'In-Person', 'woocommerce-payments' );
	}

	return __( 'Online store', 'woocommerce-payments' );
};

/**
 * Returns the bank name for a charge.
 *
 * @param {Charge} charge - The charge to get the bank name for.
 *
 * @return {string | null} The bank name for the charge.
 */
export const getBankName = ( charge: Charge ): string | null => {
	const { payment_method_details: paymentMethodDetails } = charge;
	const methodType = paymentMethodDetails?.type?.toLowerCase();

	// For card payments, get the issuer from card details
	if ( methodType === 'card' && paymentMethodDetails?.type === 'card' ) {
		// Type assertion is safe here because we've checked the type
		const cardDetails = paymentMethodDetails.card as {
			issuer?: string;
		};
		return cardDetails.issuer || null;
	}

	// For BNPL (affirm, afterpay_clearpay, klarna) disputes are all handled directly through the BNPL provider.
	// For example, with an Affirm dispute, the `issuer` is actually Affirm
	switch ( methodType ) {
		case 'affirm':
			return 'Affirm';
		case 'afterpay_clearpay':
			return 'Afterpay / Clearpay';
		case 'klarna':
			return 'Klarna';
		default:
			return null;
	}
};
