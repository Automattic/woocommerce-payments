/* global jQuery */

/**
 * Internal dependencies
 */
import './style.scss';
import { PAYMENT_METHOD_NAME_GIROPAY } from '../constants.js';
import { getConfig } from 'utils/checkout';
import WCPayAPI from './../api';
import enqueueFraudScripts from 'fraud-scripts';

jQuery( function ( $ ) {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );

	const publishableKey = getConfig( 'publishableKey' );

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	// Create an API object, which will be used throughout the checkout.
	const api = new WCPayAPI(
		{
			publishableKey,
			accountId: getConfig( 'accountId' ),
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	// In the future this object will be loaded with customer information through `wp_localize_script`.
	const preparedCustomerData = {};

	/**
	 * Block UI to indicate processing and avoid duplicate submission.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 */
	const blockUI = ( $form ) => {
		$form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
		} );
	};

	// Show error notice at top of checkout form.
	const showError = ( errorMessage ) => {
		const messageWrapper =
			'<ul class="woocommerce-error" role="alert">' +
			errorMessage +
			'</ul>';
		const $container = $(
			'.woocommerce-notices-wrapper, form.checkout'
		).first();

		if ( ! $container.length ) {
			return;
		}

		// Adapted from WooCommerce core @ ea9aa8c, assets/js/frontend/checkout.js#L514-L529
		$(
			'.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
		).remove();
		$container.prepend(
			'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
				messageWrapper +
				'</div>'
		);
		$container
			.find( '.input-text, select, input:checkbox' )
			.trigger( 'validate' )
			.blur();

		let scrollElement = $( '.woocommerce-NoticeGroup-checkout' );
		if ( ! scrollElement.length ) {
			scrollElement = $container;
		}

		$.scroll_to_notices( scrollElement );
		$( document.body ).trigger( 'checkout_error' );
	};

	// Create payment method on submission.
	let paymentMethodGenerated;

	// Handle the checkout form when WooCommerce Payments is chosen.
	$( 'form.checkout' ).on(
		'checkout_place_order_' + PAYMENT_METHOD_NAME_GIROPAY,
		function () {
			console.log("FORM CHECKOUT WITH GIROPAY");
			$( '#wcpay-payment-method' ).val( 'giropay' );
			return false;
			// if ( ! isUsingSavedPaymentMethod() ) {
			// 	return handlePaymentMethodCreation(
			// 		$( this ),
			// 		handleOrderPayment,
			// 		true
			// 	);
			// }
		}
	);

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', () => {
		console.log( 'ORDER REVIEW SUBMIT - GIROPAY' );
		return false;
		// if (
		// 	$( '#payment_method_woocommerce_payments' ).is( ':checked' ) &&
		// 	! isUsingSavedPaymentMethod()
		// ) {
		// 	return handlePaymentMethodCreation(
		// 		$( '#order_review' ),
		// 		handleOrderPayment,
		// 		true
		// 	);
		// }
	} );
} );
