/** @format */

/**
 * Internal dependencies
 */
import { displayType as transactionDisplayType } from 'wcpay/transactions/strings';
import { getTransactionPaymentMethodTitle } from 'wcpay/transactions/utils/getTransactionPaymentMethodTitle';

export const displayType = {
	charge: transactionDisplayType.charge,
	payment: transactionDisplayType.payment,
	payment_failure_refund: transactionDisplayType.payment_failure_refund,
	payment_refund: transactionDisplayType.payment_refund,
	refund: transactionDisplayType.refund,
	refund_failure: transactionDisplayType.refund_failure,
	dispute: transactionDisplayType.dispute,
	dispute_reversal: transactionDisplayType.dispute_reversal,
	fee_refund: transactionDisplayType.fee_refund,
	network_costs: transactionDisplayType.network_costs,
};

export const displayMethod = ( paymentMethodType?: string ): string =>
	paymentMethodType
		? getTransactionPaymentMethodTitle( paymentMethodType )
		: '';
