/* global jQuery */

/**
 * Internal dependencies
 */
import paymentRequestButtonUi from './button-ui';

jQuery( ( $ ) => {
	// Handle bookable products on the product page.
	let wcBookingFormChanged = false;

	$( document.body )
		.off( 'wc_booking_form_changed' )
		.on( 'wc_booking_form_changed', () => {
			wcBookingFormChanged = true;
		} );

	// Listen for the WC Bookings wc_bookings_calculate_costs event to complete
	// and add the bookable product to the cart, using the response to update the
	// payment request params with correct totals.
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( ! wcBookingFormChanged ) {
			return;
		}

		if (
			settings.url === window.booking_form_params.ajax_url &&
			// TODO ~FR: when is this called?
			settings.data.includes( 'wc_bookings_calculate_costs' ) &&
			xhr.responseText.includes( 'SUCCESS' )
		) {
			paymentRequestButtonUi.blockButton();

			wcBookingFormChanged = false;
			return wcpayPaymentRequest.addToCart().then( ( response ) => {
				wcpayPaymentRequestParams.product.total = response.total;
				wcpayPaymentRequestParams.product.displayItems =
					response.displayItems;
				// Empty the cart to avoid having 2 products in the cart when payment request is not used.
				api.paymentRequestEmptyCart( response.bookingId );

				paymentRequestButtonUi.init();

				paymentRequestButtonUi.unblockButton();
			} );
		}
	} );
} );
