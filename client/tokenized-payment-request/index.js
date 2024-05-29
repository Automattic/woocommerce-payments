/* global jQuery */
/**
 * External dependencies
 */
import { doAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';
import PaymentRequestCartApi from './cart-api';
import WooPaymentsPaymentRequest from './payment-request';
import paymentRequestButtonUi from './button-ui';
import { getPaymentRequestData } from './frontend-utils';
import './compatibility/wc-deposits';
import './compatibility/wc-order-attribution';
import './compatibility/wc-product-variations';

import '../checkout/express-checkout-buttons.scss';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if (
		getPaymentRequestData( 'has_block' ) &&
		getPaymentRequestData( 'button_context' ) !== 'pay_for_order'
	) {
		return;
	}

	const publishableKey = getPaymentRequestData( 'stripe' ).publishableKey;

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
			accountId: getPaymentRequestData( 'stripe' ).accountId,
			locale: getPaymentRequestData( 'stripe' ).locale,
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				$.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);
	const paymentRequestCartApi = new PaymentRequestCartApi();

	const wooPaymentsPaymentRequest = new WooPaymentsPaymentRequest( {
		wcpayApi: api,
		paymentRequestCartApi,
		productData: getPaymentRequestData( 'product' ) || undefined,
	} );

	// We don't need to initialize payment request on the checkout page now because it will be initialized by updated_checkout event.
	if (
		getPaymentRequestData( 'button_context' ) !== 'checkout' ||
		getPaymentRequestData( 'button_context' ) === 'pay_for_order'
	) {
		wooPaymentsPaymentRequest.init();
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
