/**
 * Internal dependencies
 */
import { TRANSACTION_PAYMENT_METHOD_TITLES } from 'wcpay/constants/payment-method';

type TransactionPaymentMethodId =
	| keyof typeof TRANSACTION_PAYMENT_METHOD_TITLES
	| string;

/**
 * Gets the display title for a payment method in the transaction context.
 *
 * @param {TransactionPaymentMethodId} paymentMethodId - The ID of the payment method or transaction type
 * @param {Record<string, { title: string }>} [config] - Optional payment method configuration. Defaults to window.wooPaymentsPaymentMethodsConfig
 * @return {string} The display title for the payment method
 */
export const getTransactionPaymentMethodTitle = (
	paymentMethodId: TransactionPaymentMethodId,
	config = window?.wooPaymentsPaymentMethodsConfig
): string => {
	return (
		config?.[ paymentMethodId ]?.title ||
		TRANSACTION_PAYMENT_METHOD_TITLES[
			paymentMethodId as keyof typeof TRANSACTION_PAYMENT_METHOD_TITLES
		] ||
		paymentMethodId
	);
};
