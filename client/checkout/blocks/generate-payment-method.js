/**
 * External dependencies
 */
import FingerprintJS from '@fingerprintjs/fingerprintjs';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_CARD } from '../constants.js';

/**
 * Handles the payment method generation action.
 *
 * @param {WCPayAPI} api The API class that is used to connect both with the server and Stripe.
 * @param {Object} elements A hash, containing all Stripe card elements.
 * @param {Object} billingData The billing data, which was collected from the checkout block.
 * @return {Object} The `onPaymentProcessing` response object, including a type and meta data/error message.
 */
const generatePaymentMethod = async ( api, elements, billingData ) => {
	let fingerprint = '';
	const request = api.generatePaymentMethodRequest( elements );

	request.setBillingDetail(
		'name',
		( billingData.first_name + ' ' + billingData.last_name ).trim()
	);
	request.setBillingDetail( 'email', billingData.email );
	request.setBillingDetail( 'phone', billingData.phone );
	request.setAddressDetail( 'city', billingData.city );
	request.setAddressDetail( 'country', billingData.country );
	request.setAddressDetail( 'line1', billingData.address_1 );
	request.setAddressDetail( 'line2', billingData.address_2 );
	request.setAddressDetail( 'postal_code', billingData.postcode );
	request.setAddressDetail( 'state', billingData.state );

	try {
		const {
			paymentMethod: { id },
		} = await request.send();

		const fraudPreventionToken = document
			.querySelector( '#wcpay-fraud-prevention-token' )
			?.getAttribute( 'value' );

		try {
			const fingerprintPublicAgent = await FingerprintJS.load( {
				monitoring: false,
			} );

			// Do not mount element if fingerprinting is not available
			if ( ! fingerprintPublicAgent ) {
				throw new Error( 'Unable to generate a fingerprint' );
			}

			const { visitorId } = await fingerprintPublicAgent.get();
			fingerprint = visitorId;
		} catch ( error ) {
			console.log( { error } );
		}

		return {
			type: 'success',
			meta: {
				paymentMethodData: {
					paymentMethod: PAYMENT_METHOD_NAME_CARD,
					'wcpay-payment-method': id,
					'wcpay-fraud-prevention-token': fraudPreventionToken ?? '',
					'wcpay-fingerprint': fingerprint,
				},
			},
		};
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
		};
	}
};

export default generatePaymentMethod;
