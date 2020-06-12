/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from '../constants.js';

const generatePaymentMethod = ( stripe, elements ) => {
	const args = {
		type: 'card',
		// Elements contains all necessary inputs.
		...elements,
		// eslint-disable-next-line camelcase
		// billing_details: loadBillingDetails(),
		// ToDo: Load billing details from the necessary props.
	};

	return stripe.createPaymentMethod( args )
		.then( function( { paymentMethod, error } ) {
			if ( error ) {
				throw error;
			}

			return paymentMethod;
		} )
		.then( function( { id } ) {
			return {
				type: 'success',
				meta: {
					paymentMethodData: {
						paymentMethod: PAYMENT_METHOD_NAME,
						// eslint-disable-next-line camelcase
						wcpay_payment_method: id,
					},
				},
			};
		} )
		.catch( function( error ) {
			throw error.message;
		} );
};

export default generatePaymentMethod;
