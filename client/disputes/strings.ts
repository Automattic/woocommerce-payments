/** @format **/
/* eslint-disable max-len */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

// Mapping of dispute reason to strings.
export const reasons: Record<
	string,
	{
		display: string;
		overview?: string[];
		summary?: string[];
		required?: string[];
		respond?: string[];
	}
> = {
	bank_cannot_process: {
		display: __( 'Bank cannot process', 'woocommerce-payments' ),
	},
	check_returned: {
		display: __( 'Check returned', 'woocommerce-payments' ),
	},
	credit_not_processed: {
		display: __( 'Credit not processed', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
			__(
				'If your customer was not refunded appropriately, you will need to accept the dispute, or resolve the issue with your customer. The credit card networks place liability for accepting disputed payments with you, the business.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'The customer claims that the purchased product was returned or the transaction was otherwise canceled, but you have not yet provided a refund or credit.',
				'woocommerce-payments'
			),
		],
		required: [
			__(
				'Demonstrate that you have refunded your customer through other means or that your customer is not entitled to a refund. You cannot issue a refund while a payment is being disputed. If you believe that your customer was entitled a refund that you did not provide, you can accept the dispute.',
				'woocommerce-payments'
			),
		],
		respond: [
			__(
				'You should first get in touch with your customer. If you understand what their complaint is, there is a chance for you to explain the misunderstanding or to make it right. If you’re able to resolve the issue with your customer, you can ask that they withdraw the dispute.',
				'woocommerce-payments'
			),
			__(
				'If the cardholder agrees to withdraw the dispute, you should still submit evidence for the dispute using the forms on the next screen. In addition to the following evidence, your submission should include correspondence with the cardholder saying they would withdraw the dispute and a written statement from their card issuer confirming that the dispute has been withdrawn.',
				'woocommerce-payments'
			),
		],
	},
	customer_initiated: {
		display: __( 'Customer initiated', 'woocommerce-payments' ),
	},
	debit_not_authorized: {
		display: __( 'Debit not authorized', 'woocommerce-payments' ),
	},
	duplicate: {
		display: __( 'Duplicate', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
			__(
				'If there were duplicate payments, you should accept the dispute. You cannot issue a refund while a payment is being disputed. The credit card networks place liability for accepting disputed payments with you, the business.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'The customer claims they were charged multiple times for the same product or service.',
				'woocommerce-payments'
			),
		],
		required: [
			__(
				'Demonstrate that each payment was for a separate product or service.',
				'woocommerce-payments'
			),
		],
		respond: [
			__(
				'Determine if your customer was incorrectly charged multiple times.',
				'woocommerce-payments'
			),
			__(
				'If they were not, collect any and all information documenting that each payment was made separately, such as copies of receipts. If the receipts don’t include the items purchased, be sure to include an itemized list. Each receipt should clearly indicate that the payments are for separate purchases of items or services. If you’ve been able to get in touch with the customer you should be sure to address any concerns they had in your evidence.',
				'woocommerce-payments'
			),
			__(
				'If there have been two or more separate payments, you should get in touch with your customer. If you understand what their complaint is, there is a chance for you to explain the misunderstanding or to make it right. If you’re able to resolve the issue with your customer, you can ask that they withdraw the dispute.',
				'woocommerce-payments'
			),
			__(
				'Even if your customer agrees to withdraw the dispute, you must still submit appropriate evidence using the forms on the next screen. Simply saying that your customer is going to withdraw the dispute is not sufficient evidence.',
				'woocommerce-payments'
			),
		],
	},
	fraudulent: {
		display: __( 'Fraudulent', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
			__(
				'If you believe the payment was actually made using a stolen credit card, you will need to accept the dispute. The credit card networks place liability for accepting fraudulent payments with you, the business.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'This is the most common reason for a dispute, and happens when a cardholder claims that they didn’t authorize the payment. This can happen if the card was lost or stolen and used to make a fraudulent purchase. It can also happen if the cardholder doesn’t recognize the payment as it appears on the billing statement from their card issuer.',
				'woocommerce-payments'
			),
		],
		required: [
			__(
				'Provide adequate payment and order details so that a legitimate customer recognizes it, or proves to the card issuer that their cardholder authorized the transaction.',
				'woocommerce-payments'
			),
		],
		respond: [
			__(
				'Try to get in touch with your customer. Sometimes people forget about payments they make or don’t recognize the way they appear on their card statement. If this is the case, ask them to contact their card issuer and let them know they no longer dispute the transaction.',
				'woocommerce-payments'
			),
			__(
				'Even if your customer agrees to withdraw the dispute, you must still submit appropriate evidence using the forms on the next screen. Simply saying that your customer is going to withdraw the dispute is not sufficient evidence.',
				'woocommerce-payments'
			),
			__(
				'It may be more efficient—and provide a better customer experience—to accept an accidental dispute and charge the customer again, if appropriate. Even when a dispute is withdrawn, it usually takes approximately 75 days to be finalized. Remember, it doesn’t matter to the card networks whether you win or lose a dispute; what matters is how many disputes a business receives, regardless of how many disputes are won.',
				'woocommerce-payments'
			),
		],
	},
	general: {
		display: __( 'General', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'This is an uncategorized dispute, so you should contact the customer for additional details to find out why the payment was disputed.',
				'woocommerce-payments'
			),
		],
	},
	incorrect_account_details: {
		display: __( 'Incorrect account details', 'woocommerce-payments' ),
	},
	insufficient_funds: {
		display: __( 'Insufficient funds', 'woocommerce-payments' ),
	},
	product_not_received: {
		display: __( 'Product not received', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
			__(
				'If you can not prove the customer received their product or service, you should accept the dispute. You cannot issue a refund while a payment is being disputed. The credit card networks place liability for accepting disputed payments with you, the business.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'The customer claims they did not receive the products or services purchased.',
				'woocommerce-payments'
			),
		],
		required: [
			__(
				'Prove that the customer received a physical product or offline service, or made use of a digital product or online service. This must have occurred prior to the date the dispute was initiated.',
				'woocommerce-payments'
			),
		],
		respond: [
			__(
				'First, get in touch with your customer. Understanding why they filed the dispute will be important for helping make sure your customer gets the product and will give you critical information to prevent this from happening to others.',
				'woocommerce-payments'
			),
			__(
				'Even if your customer agrees to withdraw the dispute, you must still submit appropriate evidence using the forms on the next screen. Simply saying that your customer is going to withdraw the dispute is not sufficient evidence.',
				'woocommerce-payments'
			),
		],
	},
	product_unacceptable: {
		display: __( 'Product unacceptable', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
			__(
				'If you can not prove the customer received their product or service as described, you should accept the dispute. You cannot issue a refund while a payment is being disputed. The credit card networks place liability for accepting disputed payments with you, the business.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'The product or service was received but was defective, damaged, or not as described.',
				'woocommerce-payments'
			),
		],
		required: [
			__(
				'Demonstrate that the product or service was delivered as described at the time of purchase.',
				'woocommerce-payments'
			),
		],
		respond: [
			__(
				'If the product or service is as described, provide specific information (invoice, contract, etc.) to refute the cardholder’s claims. Quality disputes are where the customer does not agree with the condition of merchandise or service received (e.g., a car repair situation or quality of a hotel room). There may be instances where you will need to obtain a neutral third-party opinion to help corroborate your claim against the cardholder. Provide as much specific information and documentation as possible to refute the cardholder’s claims. It is recommended that you address each point that the cardholder has made.',
				'woocommerce-payments'
			),
			__(
				'If the customer has not yet returned the product or canceled the service, provide specific information to that effect. You should double-check your incoming shipping records to verify that you have not received a return before you respond. If you have processed a credit or reversal for this transaction, provide evidence of this which includes the amount and date processed.',
				'woocommerce-payments'
			),
			__(
				'For products that have been repaired or replaced, provide evidence that the cardholder agreed to a repair or replacement, it has been received by the customer, and the repair or replacement has not since been disputed.',
				'woocommerce-payments'
			),
			__(
				'If your customer made no attempt to return the product or cancel the service, or if you provided a replacement product or service, make sure to note that as well.',
				'woocommerce-payments'
			),
			__(
				'If the customer withdraws their dispute you should still submit evidence using the forms on the next screen. Be sure to provide a letter or email from the cardholder stating that they are no longer in dispute.',
				'woocommerce-payments'
			),
		],
	},
	subscription_canceled: {
		display: __( 'Subscription canceled', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
			__(
				'If you can not prove the customer’s subscription was canceled, and or they did not follow your cancelation policy, you should accept the dispute. You cannot issue a refund while a payment is being disputed. The credit card networks place liability for accepting disputed payments with you, the business.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'The customer claims that you continued to charge them after a subscription was canceled.',
				'woocommerce-payments'
			),
		],
		required: [
			__(
				'Prove that the subscription was still active and that the customer was aware of, and did not follow, your cancellation procedure.',
				'woocommerce-payments'
			),
		],
		respond: [
			__(
				'First, get in touch with your customer. If you understand what they believe happened, there is a chance for you to explain the misunderstanding or to make it right. ',
				'woocommerce-payments'
			),
			__(
				'Even if your customer agrees to withdraw the dispute, you must still submit appropriate evidence using the forms on the next screen. Simply saying that your customer is going to withdraw the dispute is not sufficient evidence.',
				'woocommerce-payments'
			),
			__(
				'Otherwise, use the forms on the next screen to submit evidence that the subscription was still active and that the customer was aware of, and did not follow, your cancellation procedure.',
				'woocommerce-payments'
			),
		],
	},
	unrecognized: {
		display: __( 'Unrecognized', 'woocommerce-payments' ),
		overview: [
			__(
				'If you believe the dispute is invalid, you can challenge it by submitting the appropriate evidence using the response forms on the next screen.',
				'woocommerce-payments'
			),
			__(
				'If you can not prove the customer’s subscription was canceled, and or they did not follow your cancelation policy, you should accept the dispute. You cannot issue a refund while a payment is being disputed. The credit card networks place liability for accepting disputed payments with you, the business.',
				'woocommerce-payments'
			),
		],
		summary: [
			__(
				'The customer doesn’t recognize the payment appearing on their card statement.',
				'woocommerce-payments'
			),
		],
		required: [
			__(
				'As with fraudulent disputes, get your customer to withdraw the dispute by helping them identify the payment. Otherwise challenge the dispute with appropriate evidence that proves the purchase was legitimate. ',
				'woocommerce-payments'
			),
		],
		respond: [
			__(
				'First, try to get in touch with your customer. Sometimes people forget about payments they make or don’t recognize the way they appear on their card statement. If this is the case, ask them to contact their card issuer and let them know they no longer dispute the transaction. Even if your customer agrees to withdraw the dispute, you must still submit appropriate evidence. Simply saying that your customer is going to withdraw the dispute is not sufficient evidence.',
				'woocommerce-payments'
			),
			__(
				'It may be more efficient—and provide a better customer experience—to accept an accidental dispute and charge the customer again, if appropriate. Even when a dispute is withdrawn, it usually takes approximately 75 days to be finalized. Remember, it doesn’t matter to the card networks whether you win or lose a dispute; what matters is how many disputes a business receives, regardless of how many disputes are won.',
				'woocommerce-payments'
			),
		],
	},
};
