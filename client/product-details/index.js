/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

const bnplPaymentMessageElement = initializeBnplSiteMessaging();

jQuery( function ( $ ) {
	$( '.quantity input' ).on( 'change', function ( event ) {
		const newQuantity = event.target.value;
		const price = window.wcpayStripeSiteMessaging.price;
		bnplPaymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
		} );
	} );

	// Handle BNPL payment message changes for product variation.
	$( '.single_variation_wrap' ).on( 'show_variation', function (
		event,
		variation
	) {
		const quantity = $( '.quantity input' ).val();
		// The multiplier is fetched within wcpayStripeSiteMessaging global variable, to handle any presence of zero decimal currencies.
		const { multiplier } = window.wcpayStripeSiteMessaging;

		window.wcpayStripeSiteMessaging.price =
			variation.display_price * multiplier;
		bnplPaymentMessageElement.update( {
			amount: parseInt(
				variation.display_price * quantity * multiplier,
				10
			),
		} );
	} );

	$( '.reset_variations' ).on( 'click', function () {
		bnplPaymentMessageElement.update( {
			amount: 0,
		} );
	} );
} );
