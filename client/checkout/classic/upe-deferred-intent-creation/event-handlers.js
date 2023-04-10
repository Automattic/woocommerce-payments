/* global jQuery */

/**
 * Internal dependencies
 */
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	isUsingSavedPaymentMethod,
} from '../../utils/upe';
import {
	checkout,
	initializeFingerprint,
	mountStripePaymentElement,
} from './stripe-checkout';

jQuery( function ( $ ) {
	$( document.body ).on( 'updated_checkout', () => {
		initializeFingerprint();
		if (
			$( '.wcpay-upe-element' ).length &&
			! $( '.wcpay-upe-element' ).children().length
		) {
			$( '.wcpay-upe-element' )
				.toArray()
				.forEach( ( domElement ) =>
					mountStripePaymentElement( domElement )
				);
		}
	} );

	$( 'form.checkout' ).on( generateCheckoutEventNames(), function () {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return checkout( jQuery( this ), paymentMethodType );
		}
	} );
} );
