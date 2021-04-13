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

	/**
	 * Saves the payment method ID in a hidden input, and re-submits the form.
	 *
	 * @param {Object} $form         The jQuery object for the form.
	 * @param {Object} paymentMethod Payment method object.
	 */
	const handleOrderPayment = ( $form, { id } ) => {
		// Populate form with the payment method.
		$( '#wcpay-payment-method' ).val( id );

		// Re-submit the form.
		$form.removeClass( 'processing' ).submit();
	};

	/**
	 * Generates a payment method, saves its ID in a hidden input, and re-submits the form.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @param {Function} successHandler    Callback to be executed when payment method is generated.
	 * @param {boolean}  useBillingDetails Flag to control whether to use from billing details or not.
	 * @return {boolean} A flag for the event handler.
	 */
	const handlePaymentMethodCreation = (
		$form,
		successHandler,
		useBillingDetails
	) => {
		// We'll resubmit the form after populating our payment method, so if this is the second time this event
		// is firing we should let the form submission happen.
		if ( paymentMethodGenerated ) {
			paymentMethodGenerated = null;
			return;
		}

		blockUI( $form );

		const request = api.generatePaymentMethodRequest(
			{
				type: 'giropay',
			},
			preparedCustomerData
		);

		// Populate the necessary billing details.
		if ( useBillingDetails ) {
			request.setBillingDetail(
				'name',
				(
					$( '#billing_first_name' ).val() +
					' ' +
					$( '#billing_last_name' ).val()
				).trim()
			);
			request.setBillingDetail( 'email', $( '#billing_email' ).val() );
			request.setBillingDetail( 'phone', $( '#billing_phone' ).val() );
			request.setAddressDetail( 'city', $( '#billing_city' ).val() );
			request.setAddressDetail(
				'country',
				$( '#billing_country' ).val()
			);
			request.setAddressDetail(
				'line1',
				$( '#billing_address_1' ).val()
			);
			request.setAddressDetail(
				'line2',
				$( '#billing_address_2' ).val()
			);
			request.setAddressDetail(
				'postal_code',
				$( '#billing_postcode' ).val()
			);
			request.setAddressDetail( 'state', $( '#billing_state' ).val() );
		}

		request
			.send()
			.then( ( { paymentMethod } ) => {
				// Flag that the payment method has been successfully generated so that we can allow the form
				// submission next time.
				paymentMethodGenerated = true;

				successHandler( $form, paymentMethod );
			} )
			.catch( ( error ) => {
				$form.removeClass( 'processing' ).unblock();
				showError( error.message );
			} );

		// Prevent form submission so that we can fire it once a payment method has been generated.
		return false;
	};

	// Handle the checkout form when WooCommerce Payments is chosen.
	$( 'form.checkout' ).on(
		'checkout_place_order_' + PAYMENT_METHOD_NAME_GIROPAY,
		function () {
			return handlePaymentMethodCreation(
				$( this ),
				handleOrderPayment,
				true
			);
		}
	);
} );
