/**
 * Internal dependencies
 */
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {WCPayAPI} api            The API used for connection both with the server and Stripe.
 * @param {string}   redirectUrl    The URL to redirect to after confirming the intent on Stripe.
 * @param {boolean}  paymentNeeded  A boolean whether a payment or a setup confirmation is needed.
 * @param {string|null}   paymentIntentSecret Payment Intent Secret used to validate payment on rate limit error.
 * @param {Object}   elements       Reference to the UPE elements mounted on the page.
 * @param {Object}   billingData    An object containing the customer's billing data.
 * @param {Object}   emitResponse   Various helpers for usage with observer response objects.
 * @param {string}   selectedUPEPaymentType   The selected UPE payment type.
 * @return {Object}                An object, which contains the result from the action.
 */
export default async function confirmUPEPayment(
	api,
	redirectUrl,
	paymentNeeded,
	paymentIntentSecret,
	elements,
	billingData,
	emitResponse,
	selectedUPEPaymentType
) {
	const name =
		`${ billingData.first_name } ${ billingData.last_name }`.trim() || '-';

	try {
		const confirmParams = {
			return_url: redirectUrl,
			payment_method_data: {
				billing_details: {
					name,
					email:
						'string' === typeof billingData.email
							? billingData.email.trim()
							: '-',
					phone: billingData.phone || '-',
					address: {
						country: billingData.country || '-',
						postal_code: billingData.postcode || '-',
						state: billingData.state || '-',
						city: billingData.city || '-',
						line1: billingData.address_1 || '-',
						line2: billingData.address_2 || '-',
					},
				},
			},
		};

		// Afterpay requires shipping details to be passed. Not needed by other payment methods.
		if ( PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY === selectedUPEPaymentType ) {
			confirmParams.shipping = {
				name,
				phone: billingData.phone || '-',
				address: {
					country: billingData.country || '_',
					postal_code: billingData.postcode || '-',
					state: billingData.state || '-',
					city: billingData.city || '-',
					line1: billingData.address_1 || '-',
					line2: billingData.address_2 || '-',
				},
			};
		}

		if ( paymentNeeded ) {
			const { error } = await api.handlePaymentConfirmation(
				elements,
				confirmParams,
				paymentIntentSecret
			);
			if ( error ) {
				throw error;
			}
		} else {
			const { error } = await api.getStripe().confirmSetup( {
				elements,
				confirmParams,
			} );
			if ( error ) {
				throw error;
			}
		}
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
			messageContext: emitResponse.noticeContexts.PAYMENTS,
		};
	}
}
