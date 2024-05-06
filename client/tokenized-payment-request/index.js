/* global jQuery, wcpayPaymentRequestParams */
/**
 * External dependencies
 */
import { doAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';
import PaymentRequestCartApi from './cart-api';
import WcpayPaymentRequest from './payment-request';
import paymentRequestButtonUi from './button-ui';
import './compatibility/wc-deposits';
import './compatibility/wc-product-variations';

import '../checkout/express-checkout-buttons.scss';

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
		$container: $( '#wcpay-payment-request-button' ),
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
				$.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);
	const paymentRequestCartApi = new PaymentRequestCartApi();

	const wcpayPaymentRequest = new WcpayPaymentRequest( {
		wcpayApi: api,
		paymentRequestCartApi,
		productData: wcpayPaymentRequestParams.product || undefined,
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
		doAction( 'wcpay.payment-request.update-button-data' );
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		doAction( 'wcpay.payment-request.update-button-data' );
	} );
} );
