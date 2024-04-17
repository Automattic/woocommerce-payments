/* global jQuery */

/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import paymentRequestButtonUi from './button-ui';

jQuery( ( $ ) => {
	// TODO ~FR handle booking form changed events
	return;
	// Handle bookable products on the product page.
	let wcBookingFormChanged = false;

	$( document.body ).on( 'wc_booking_form_changed', () => {
		wcBookingFormChanged = true;
	} );

	// When the `wc_booking_form_changed` event is fired, a `wc_bookings_calculate_costs` request is also made to the backend.
	// This request is made to calculate the new costs on the frontend by the WC bookings plugin.
	// Once this request is made, we add the product to the cart (with its selected information)
	// in order to update the PRB button with new totals, shipping needs, etc.
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( ! wcBookingFormChanged ) {
			return;
		}

		if (
			settings.url === window.booking_form_params.ajax_url &&
			settings.data.includes( 'wc_bookings_calculate_costs' ) &&
			xhr.responseText.includes( 'SUCCESS' )
		) {
			paymentRequestButtonUi.blockButton();

			wcBookingFormChanged = false;

			// TODO: make a request to the backend to add the newly selected product to the cart and update the PRB button with the new information.
			wcpayPaymentRequest.init();

			paymentRequestButtonUi.unblockButton();
		}
	} );
} );

addFilter(
	'wcpay.payment-request.cart-add-item',
	'automattic/wcpay/payment-request',
	( productData ) => {
		const productId = jQuery( '.wc-booking-product-id' ).val();
		if ( ! jQuery( '.wc-bookings-booking-form' ).length || ! productId ) {
			return productData;
		}

		return {
			...productData,
			id: parseInt( productId, 10 ),
		};
	}
);
