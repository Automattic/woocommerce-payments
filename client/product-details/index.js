/* global jQuery */
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
		window.wcpayStripeSiteMessaging.price = variation.display_price * 100;
		bnplPaymentMessageElement.update( {
			amount: parseInt( variation.display_price * quantity * 100, 10 ),
		} );
	} );

	$( '.reset_variations' ).on( 'click', function () {
		bnplPaymentMessageElement.update( {
			amount: 0,
		} );
	} );
} );
