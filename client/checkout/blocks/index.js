/**
 * External dependencies
 */
import { Component } from 'react';
import { useEffect } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { loadStripe } from '@stripe/stripe-js';
import {
	Elements,
	CardElement,
	useElements,
	ElementsConsumer,
} from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import WCPayAPI from './../api';

const PAYMENT_METHOD_NAME = 'woocommerce_payments';

const api = new WCPayAPI( {
	publishableKey: 'pk_test_CkBd4k7oYuaZehsDaHv5UhE800N3BquMR9',
	accountId: 'acct_1GSOo8LgDSN7Ly3j',
} );
const stripe = api.getStripe();

// const stripe = loadStripe( 'pk_test_CkBd4k7oYuaZehsDaHv5UhE800N3BquMR9', {
// 	stripeAccount: 'acct_1GSOo8LgDSN7Ly3j',
// } );

// eslint-disable-next-line no-undef
// const stripe = new Stripe( 'pk_test_CkBd4k7oYuaZehsDaHv5UhE800N3BquMR9', {
// 	/* eslint-disable-next-line camelcase */
// 	stripeAccount: 'acct_1GSOo8LgDSN7Ly3j',
// } );

const generatePaymentMethod = ( elements ) => {
	const args = {
		type: 'card',
		card: elements.getElement( CardElement ),
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

const WCPayFields = ( { elements, eventRegistration: { onPaymentProcessing, onCheckoutAfterProcessingWithSuccess } } ) => {
	useEffect( () => {
		return onPaymentProcessing( () => {
			return generatePaymentMethod( elements );
		} );
	}, [ elements, stripe ] );

	useEffect( () => {
		return onCheckoutAfterProcessingWithSuccess( ( { processingResponse: { paymentDetails } } ) => {
			const { redirect } = paymentDetails;

			var partials = redirect.match( /^#wcpay-confirm-pi:(.+):(.+)$/ );
			if ( ! partials ) {
				return { type: 'success' };
			}

			var orderId = partials[ 1 ];
			var clientSecret = partials[ 2 ];

			return stripe.confirmCardPayment( clientSecret )
				.then( ( result ) => {
					const wcpay_config = {
						ajaxUrl: 'http://localhost:8082/wp-admin/admin-ajax.php',
						updateOrderStatusNonce: '6724c8f376',
					};

					const ajaxCall = jQuery.post( wcpay_config.ajaxUrl, {
						action: 'update_order_status',
						// eslint-disable-next-line camelcase
						order_id: orderId,
						// eslint-disable-next-line camelcase
						_ajax_nonce: wcpay_config.updateOrderStatusNonce,
					} );

					return [ ajaxCall, result.error ];
				} )
				.then( async ( [ verificationCall, originalError ] ) => {
					if ( originalError ) {
						throw originalError;
					}

					const response = await verificationCall;

					if ( response.error ) {
						throw response.error;
					}

					return {
						type: 'success',
						redirectUrl: response.return_url,
					};
				} )
				.catch( ( err ) => {
					// ToDo: Display the error in the right place somehow.
				} );
		} );
	}, [ elements, stripe ] );

	const options = {
		hidePostalCode: true,
		classes: {
			base: 'wcpay-card-mounted',
		},
	};

	return <CardElement options={ options } />;
};

// class WCPayFields extends Component {


// 	componentDidMount() {
// 		console.log('mounted');

// 		const { elements } = this.props;

// 		// useEffect( () => {
// 		// 	const { eventRegistration: { onPaymentProcessing } } = this.props;

// 		// 	onPaymentProcessing( () => {
// 		// 		// console.log(elements.getElement(CardNumberElement));
// 		// 		return this.prepareToken();
// 		// 	} );
// 		// }, [ this.props.elements ] );
// 	}

// 	prepareToken() {
// 		return new Promise( ( resolve, reject ) => {
// 			setTimeout( () => {
// 				confirm( 'Should it succeed?' )
// 					? resolve( true )
// 					: reject( false );
// 			}, 1 );
// 		} );
// 	}
// }

const ConsumableWCPayFields = ( props ) => (
	<Elements stripe={ stripe }>
		<ElementsConsumer>
			{ ( { elements } ) => (
				<WCPayFields elements={ elements } { ...props } />
			) }
		</ElementsConsumer>
	</Elements>
);

registerPaymentMethod(
	( PaymentMethodConfig ) => new PaymentMethodConfig( {
		name: PAYMENT_METHOD_NAME,
		content: <ConsumableWCPayFields />,
		edit: <ConsumableWCPayFields />,
		canMakePayment: () => true,
		paymentMethodId: PAYMENT_METHOD_NAME,
		label: 'Credit Card',
		ariaLabel: 'Credit Card',
		placeOrderButtonLabel: 'Place order with WCPay',
	} )
);
