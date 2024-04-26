/* global jQuery, wcpayPaymentRequestParams */
/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';

import paymentRequestButtonUi from './button-ui';
import './wc-product-variations-compatibility';
import './wc-deposits-compatibility';
import '../checkout/express-checkout-buttons.scss';

import PaymentRequestCartApi from './cart-api';
import WcpayPaymentRequest from './payment-request';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if (
		wcpayPaymentRequestParams.has_block &&
		wcpayPaymentRequestParams.button_context !== 'pay_for_order'
	) {
		return;
	}

	const publishableKey = wcpayPaymentRequestParams.stripe.publishableKey;

	if ( ! publishableKey ) {
		// If no configuration is present, we can't do anything.
		return;
	}

	// initializing the UI's container.
	paymentRequestButtonUi.init( {
		$container: jQuery( '#wcpay-payment-request-button' ),
	} );

	const api = new WCPayAPI(
		{
			publishableKey,
			accountId: wcpayPaymentRequestParams.stripe.accountId,
			locale: wcpayPaymentRequestParams.stripe.locale,
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);
	const paymentRequestCartApi = new PaymentRequestCartApi();

	const wcpayPaymentRequest = new WcpayPaymentRequest( {
		wcpayApi: api,
		paymentRequestCartApi,
	} );

	// We don't need to initialize payment request on the checkout page now because it will be initialized by updated_checkout event.
	if (
		wcpayPaymentRequestParams.button_context !== 'checkout' ||
		wcpayPaymentRequestParams.button_context === 'pay_for_order'
	) {
		wcpayPaymentRequest.init();
	}

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		wcpayPaymentRequest.init( { refresh: true } );
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		wcpayPaymentRequest.init( { refresh: true } );
	} );
} );
