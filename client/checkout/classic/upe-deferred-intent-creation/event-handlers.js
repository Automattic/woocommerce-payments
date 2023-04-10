/* global jQuery */

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	isUsingSavedPaymentMethod,
} from '../../utils/upe';
import { checkout, mountStripePaymentElement } from './stripe-checkout';
import enqueueFraudScripts from 'fraud-scripts';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	$( document.body ).on( 'updated_checkout', () => {
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
