/* global jQuery */
/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import WCPayAPI from '../checkout/api';
import PaymentRequestCartApi from './cart-api';
import PaymentRequestOrderApi from './order-api';
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
	let paymentRequestCartApi = new PaymentRequestCartApi();
	if ( getPaymentRequestData( 'button_context' ) === 'pay_for_order' ) {
		paymentRequestCartApi = new PaymentRequestOrderApi( {
			orderId: getUPEConfig( 'order_id' ),
			key: getUPEConfig( 'key' ),
			billingEmail: getUPEConfig( 'billing_email' ),
		} );
	}

	const wooPaymentsPaymentRequest = new WooPaymentsPaymentRequest( {
		wcpayApi: api,
		paymentRequestCartApi,
		productData: getPaymentRequestData( 'product' ) || undefined,
	} );

	wooPaymentsPaymentRequest.init();

	// When the cart is updated, the PRB is removed from the page and needs to be re-initialized.
	$( document.body ).on( 'updated_cart_totals', async () => {
		await applyFilters(
			'wcpay.payment-request.update-button-data',
			Promise.resolve()
		);
		wooPaymentsPaymentRequest.init();
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', async () => {
		await applyFilters(
			'wcpay.payment-request.update-button-data',
			Promise.resolve()
		);
	} );
} );
