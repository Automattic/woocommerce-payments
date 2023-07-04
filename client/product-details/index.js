/* global jQuery */
/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

const bnplPaymentMessageElement = initializeBnplSiteMessaging();

jQuery( function ( $ ) {
	// Update BNPL payment message when quantity is changed.
	$( '.quantity input' ).on( 'change', function ( event ) {
		const newQuantity = event.target.value;
		const price = window.wcpayStripeSiteMessaging.price;
		bnplPaymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
		} );
	} );

	// Update BNPL payment message when variation is changed in a variable product.
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
} );
